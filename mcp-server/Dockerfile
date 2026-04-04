FROM node:20-alpine AS build

WORKDIR /app

COPY package.json package-lock.json tsconfig.json ./
RUN npm ci

COPY src ./src
RUN npm run build

FROM node:20-alpine AS runtime

WORKDIR /app
ENV NODE_ENV=production
# Railway routes public HTTP to PORT; stdio transport never binds → 502 without this.
ENV TRANSPORT_TYPE=sse

COPY package.json package-lock.json ./
RUN npm ci --omit=dev

COPY --from=build /app/build ./build

EXPOSE 8080

CMD ["npm", "run", "start"]
