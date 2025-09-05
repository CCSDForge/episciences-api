FROM httpd:2.4-alpine

# Enable necessary modules
RUN sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /usr/local/apache2/conf/httpd.conf && \
    sed -i 's/#LoadModule deflate_module/LoadModule deflate_module/' /usr/local/apache2/conf/httpd.conf && \
    sed -i 's/#LoadModule expires_module/LoadModule expires_module/' /usr/local/apache2/conf/httpd.conf && \
    sed -i 's/#LoadModule headers_module/LoadModule headers_module/' /usr/local/apache2/conf/httpd.conf && \
    sed -i 's/#LoadModule ssl_module/LoadModule ssl_module/' /usr/local/apache2/conf/httpd.conf

# Enable PHP-FPM proxy module
RUN echo "LoadModule proxy_module modules/mod_proxy.so" >> /usr/local/apache2/conf/httpd.conf
RUN echo "LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so" >> /usr/local/apache2/conf/httpd.conf

# Set ServerName to avoid warnings and add SSL Listen directive
RUN echo "ServerName api-dev.episciences.org" >> /usr/local/apache2/conf/httpd.conf && \
    echo "Listen 443 ssl" >> /usr/local/apache2/conf/httpd.conf && \
    echo "Include conf/vhost.conf" >> /usr/local/apache2/conf/httpd.conf && \
    echo "Include conf/vhost-ssl.conf" >> /usr/local/apache2/conf/httpd.conf

EXPOSE 80 443