<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Autenticacion contra Authentik (OpenID Connect)
|--------------------------------------------------------------------------
|
| La API nunca ve la contrasena del usuario: manda a Authentik y recibe un
| codigo. Eso permite que el mismo Authentik sirva para varias empresas sin que
| esta aplicacion guarde ningun secreto de acceso.
|
| `issuer` es la URL del proveedor tal como la declara Authentik. La
| aplicacion NO descubre endpoints por su cuenta: los pide al documento de
| descubrimiento, en DescubridorOidc. Escribir las URLs a mano es lo que hace
| que el mismo codigo no sirva para desarrollo y para produccion.
|
*/

return [
    'issuer' => env('AUTHENTIK_ISSUER'),

    'client_id' => env('AUTHENTIK_CLIENT_ID'),

    'client_secret' => env('AUTHENTIK_CLIENT_SECRET'),

    'redirect_uri' => env('AUTHENTIK_REDIRECT_URI'),

    // El alcance minimo que hace falta: identificar a la persona y saber su
    // correo. Pedir más alcances que esos es pedir datos personales de más.
    'scopes' => array_filter(explode(' ', (string) env('AUTHENTIK_SCOPES', 'openid email profile'))),

    // RS256 es el valor por defecto de Authentik. No se acepta `none` ni
    // algoritmos simetricos: un id_token sin firma de verdad es un token que
    // cualquiera puede escribir.
    'algorithm' => env('AUTHENTIK_ALGORITHMO', 'RS256'),

    'sesion_lifetime' => (int) env('SESION_LIFETIME', 480),
];