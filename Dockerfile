FROM node:20-slim AS builder

WORKDIR /app
COPY package.json ./
RUN npm install
COPY . .

# ── Build-time переменные (VITE_* запекаются в бандл при сборке) ──────────
ARG VITE_B24_CLIENT_ID
ARG VITE_B24_CLIENT_SECRET
ARG VITE_B24_REDIRECT_URI
ARG VITE_API_BASE_URL
ARG VITE_B24_BASE_URL

ENV VITE_B24_CLIENT_ID=$VITE_B24_CLIENT_ID
ENV VITE_B24_CLIENT_SECRET=$VITE_B24_CLIENT_SECRET
ENV VITE_B24_REDIRECT_URI=$VITE_B24_REDIRECT_URI
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL
ENV VITE_B24_BASE_URL=$VITE_B24_BASE_URL

RUN npm run build

FROM node:20-slim

WORKDIR /app
COPY package.json ./
RUN npm install --omit=dev
COPY --from=builder /app/dist ./dist
COPY server.cjs ./

EXPOSE 8080
CMD ["node", "server.cjs"]
