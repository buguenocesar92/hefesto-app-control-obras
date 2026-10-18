<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Auth\DescubridorOidc;
use App\Services\Auth\ResolvedorEmpresa;
use App\Services\Auth\VerificadorIdToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Inicio y cierre de sesión contra Authentik (OpenID Connect).
 *
 * El flujo es el estandar: la API manda al usuario a Authentik, Authentik lo
 * autentica y devuelve un codigo, la API canjea el codigo por tokens y
 * verifica la firma del `id_token`.
 *
 * La API nunca ve la contrasena del usuario. Eso permite que el mismo
 * Authentik sirva para varias empresas sin que esta aplicación guarde
 * ningun secreto de acceso.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly DescubridorOidc $descubridor,
        private readonly VerificadorIdToken $verificador,
        private readonly ResolvedorEmpresa $resolvedor,
    ) {}

    /** Empresas disponibles en la pantalla de entrada. */
    public function empresas(Request $request): Response
    {
        return response()->json([
            'empresas' => $this->resolvedor->empresasPublicas(),
        ]);
    }

    /**
     * Datos de la persona conectada.
     *
     * No devuelve RUT ni teléfono: la sesión de la interfaz es lo único que
     * viaja al navegador, y esos datos no hacen falta para operar la obra.
     * Ver `Usuario::$hidden`.
     */
    public function perfil(Request $request): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return response()->json(['mensaje' => 'No hay sesion.'], 401);
        }

        return response()->json([
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'correo' => $usuario->correo,
            'cargo' => $usuario->cargo,
            'rol' => $usuario->rol->value,
            'activo' => $usuario->activo,
        ]);
    }

    /** Redirige a Authentik. */
    public function entrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'empresa' => ['required', 'string', 'max', '60'],
        ]);

        $estado = Str::random(40);

        // El estado va en la sesión y se compara en el retorno: es lo que
        // impide que alguien enganche a la victima con una URL construida por
        // un atacante.
        $request->session()->put('oidc_estado', $estado);
        $request->session()->put('empresa_slug', $datos['empresa']);

        $nonce = Str::random(40);
        $request->session()->put('oidc_nonce', $nonce);

        $url = $this->descubridor->urlDeAutorizacion();

        return redirect()->away($url . '?' . http_build_query([
            'client_id' => config('authentik.client_id'),
            'redirect_uri' => config('authentik.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', config('authentik.scopes')),
            'state' => $estado,
            'nonce' => $nonce,
        ]));
    }

    /** Retorno de Authentik: canjea el codigo y abre sesión. */
    public function retorno(Request $request): RedirectResponse
    {
        $estadoEsperado = $request->session()->pull('oidc_estado');
        $nonceEsperado = $request->session()->pull('oidc_nonce');
        $slugEmpresa = $request->session()->pull('empresa_slug');

        if ($request->query('state') !== $estadoEsperado || $request->query('state') === null) {
            abort(400, 'El estado del inicio de sesion no coincide.');
        }

        $codigo = $request->query('code');

        if (! is_string($codigo) || $codigo === '') {
            abort(400, 'Authentik no devolvio un codigo de autorizacion.');
        }

        try {
            $reclamos = $this->verificador->verificar($this->canjearPorTokens($codigo));

            $usuario = $this->resolvedor->resolver((string) $slugEmpresa, $reclamos);
        } catch (Throwable $e) {
            report($e);

            return redirect('/entrar?error=' . urlencode('No se pudo iniciar sesion.'));
        }

        // El nonce viaja dentro del id_token; se compara con el de la sesión.
        if (($reclamos['nonce'] ?? null) !== $nonceEsperado) {
            abort(400, 'El token no corresponde a este inicio de sesion.');
        }

        Auth::guard('web')->login($usuario);

        $request->session()->regenerate();

        return redirect('/obras');
    }

    /** Cierre de sesión local y en Authentik. */
    public function salir(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/entrar');
    }

    /**
     * Canjea el codigo de autorizacion por tokens y devuelve el `id_token`.
     */
    private function canjearPorTokens(string $codigo): string
    {
        $respuesta = Http::asForm()->timeout(10)->post($this->descubridor->urlDeToken(), [
            'grant_type' => 'authorization_code',
            'code' => $codigo,
            'redirect_uri' => config('authentik.redirect_uri'),
            'client_id' => config('authentik.client_id'),
            'client_secret' => config('authentik.client_secret'),
        ]);

        if (! $respuesta->successful()) {
            throw new \RuntimeException('Authentik no acepto el canje del codigo de autorizacion.');
        }

        $idToken = $respuesta->json('id_token');

        if (! is_string($idToken) || $idToken === '') {
            throw new \RuntimeException('Authentik no devolvio un id_token.');
        }

        return $idToken;
    }
}