#!/bin/sh
set -eu

# Generate site.conf from template using PORT env var
PORT=${PORT:-8000}
export PORT
envsubst '$PORT' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default

# Create symlink to sites-enabled
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Debug output
echo "Generated site.conf with PORT=$PORT"
echo "Symlink status:"
ls -la /etc/nginx/sites-enabled/default
echo "listen directive:"
grep "listen" /etc/nginx/sites-available/default
