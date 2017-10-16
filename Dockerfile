# Use a PHP/Composer published image as a parent image
FROM maat8/php7-composer

# Set the working directory to /usr/src
WORKDIR /usr/src

# Copy source files and directories to the working directory
ADD . /usr/src

# Install composer dependencies
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts

# Make port 80 available to the world outside this container
EXPOSE 8888

# Example command to startup service (php -S localhost:8888 -t . index.php)
# Run index.php when the container launches
CMD ["php", "-S", "0.0.0.0:8888", "-t", "./", "index.php"]
