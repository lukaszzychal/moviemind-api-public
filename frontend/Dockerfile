# Stage 1: Build the Vue.js application
FROM node:20-alpine AS build
WORKDIR /app

# Install dependencies
# Using npm ci requires package-lock.json but npm install is safer if lockfile is missing
COPY package*.json ./
RUN npm install

# Copy project files and build
COPY . .
RUN npm run build

# Stage 2: Serve the application with Nginx
FROM nginx:alpine

# Copy custom Nginx configuration for SPA routing
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Copy built assets from the build stage
COPY --from=build /app/dist /usr/share/nginx/html

# Expose port (Railway dynamically maps the EXPOSEd port)
EXPOSE 80

# Start Nginx
CMD ["nginx", "-g", "daemon off;"]
