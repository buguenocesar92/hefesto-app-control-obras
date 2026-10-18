# ---------------------------------------------------------------------------
# Imagen de la interfaz (Nuxt con SSR)
# ---------------------------------------------------------------------------
# El SSR es obligatorio: la interfaz se sirve renderizada desde Node, y es Node
# quien habla con la API. El navegador nunca ve un token ni la dirección interna
# de la base.
# ---------------------------------------------------------------------------

FROM node:22.13-alpine AS construcción

WORKDIR /app

COPY web/package.json web/package-lock.json* ./

# Se copian primero los manifiestos: mientras no cambien, la capa de
# dependencias no se rehace en cada despliegue.
RUN npm ci

COPY web/ ./

RUN npm run build

# ---------------------------------------------------------------------------

FROM node:22.13-alpine AS ejecucion

WORKDIR /app

ENV NODE_ENV=production
ENV NITRO_HOST=0.0.0.0
ENV NITRO_PORT=3000

COPY --from=construcción /app/.output ./.output

# Node corre como usuario sin privilegios. La imagen oficial trae el usuario
# `node` ya creado.
USER node

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]