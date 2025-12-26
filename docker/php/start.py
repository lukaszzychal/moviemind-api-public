#!/usr/bin/env python3
"""
Start script for Distroless production container.
This script replaces the bash start.sh script since Distroless doesn't have a shell.
"""
import os
import subprocess
import sys
import time

def main():
    # Set PORT from environment (default: 80)
    port = os.environ.get('PORT', '80')
    
    # Update Nginx config with PORT
    nginx_config = '/etc/nginx/http.d/default.conf'
    try:
        with open(nginx_config, 'r') as f:
            content = f.read()
        # Replace listen directive
        content = content.replace('listen 80', f'listen {port}')
        # Ensure fastcgi_pass uses 127.0.0.1:9000 (not php:9000)
        content = content.replace('fastcgi_pass php:9000', 'fastcgi_pass 127.0.0.1:9000')
        with open(nginx_config, 'w') as f:
            f.write(content)
        print(f"✅ Nginx config updated with PORT={port}")
    except Exception as e:
        print(f"⚠️  Could not update Nginx config: {e}")
    
    # Verify Laravel public directory exists
    if not os.path.exists('/var/www/html/public'):
        print("❌ ERROR: /var/www/html/public directory not found!")
        sys.exit(1)
    print("✅ Laravel public directory verified")
    
    # Create necessary directories
    directories = [
        '/var/log/nginx',
        '/run/nginx',
        '/var/log/supervisor',
        '/var/www/html/storage/framework/cache',
        '/var/www/html/storage/framework/sessions',
        '/var/www/html/storage/framework/views',
        '/var/www/html/storage/logs',
        '/var/www/html/bootstrap/cache',
    ]
    for directory in directories:
        try:
            os.makedirs(directory, exist_ok=True)
        except Exception as e:
            print(f"⚠️  Could not create {directory}: {e}")
    
    # Start Supervisor (which will start PHP-FPM and Nginx)
    print("Starting Supervisor...")
    print(f"Nginx will listen on port {port}")
    print("PHP-FPM will listen on 127.0.0.1:9000")
    
    # Execute supervisor
    os.execv('/usr/bin/supervisord', ['/usr/bin/supervisord', '-c', '/etc/supervisord.conf'])

if __name__ == '__main__':
    main()

