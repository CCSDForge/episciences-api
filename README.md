# episciences-api
## About REST Episciences API

The REST Episciences API can be used with **authentication and authorisation**.

Authentication is reserved exclusively for users of one of the Episciences journals, so, it is vital to recover
your **API password** - once you have logged on to the journal site - via **My space > Reset my API password**.

### Get the authentication token with a POST request

#### The API is called up securely via a JWT token, which can be retrieved via "/api/login" endpoint: 

> **_NOTE:_** `About "code": you have to use only the journal's code (e.g. "code": "epijinfo")`.

```
curl -X 'POST' \
'https://api-preprod.episciences.org/api/login' \
-H 'accept: application/json' \
-H 'Content-Type: application/json' \
-d '{
"username": "login",
"password": "api pwd",
"code": "journal’s code (e.g. epijinfo)"
}'
```

#### Response type:

{"token":"eyJ0eXAiOiJKV1….", refresh_token":"3ff818151dd…"}

The token expires after **one hour**.
The refresh_token expires after **one month**.

It is possible to generate a new token without asking the user to enter these identifiers via "api/token/refresh":

```
curl -X 'POST' \
'https://api-preprod.episciences.org/api/token/refresh' \
-H 'accept: application/json' \
-H 'Content-Type: application/json' \
-d '{
"refresh_token": "3ff818151dd…"
}'
```


#### To check that you're connected and as an example of how to use it: 

```
curl -X 'GET' \
'https://api-preprod.episciences.org/api/me' \
-H 'accept: application/ld+json' \
-H 'Authorization: Bearer eyJ0eXAiOiJKV1….'
```

**To explore the available endpoints: https://api-preprod.episciences.org/api**

## Testing the API Manually

1. Expand /api/login, click the Try it out button and enter your account information.
2. Next, press the execute button, it will respond with a failed or passed result.
3. In this case, we get the passed result response, with response code 200.
4. Take the token string and put it in Authorize.
5. After the authorization step, we are now ready to test the API

## Development Setup

This section covers setting up the Episciences API for local development using Docker.

### Prerequisites

Before starting, ensure you have the following installed:

- **Docker** and **Docker Compose** - [Install Docker](https://docs.docker.com/get-docker/)
- **Git** - For cloning the repository
- **OpenSSL** - For SSL certificate generation (automatically checked by Makefile)

### Quick Start

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd episciences-api
   ```

2. **Start the development environment:**
   ```bash
   make docker-up
   ```
   This command will:
   - Automatically generate SSL certificates for HTTPS development
   - Start all required containers (PHP-FPM, Apache, MySQL)
   - Set up the complete development environment

3. **Add the local domain to your hosts file:**
   ```bash
   echo "127.0.0.1 api-dev.episciences.org" | sudo tee -a /etc/hosts
   ```

4. **Access the application:**
   - **API Documentation:** https://api-dev.episciences.org:8443/api/docs
   - **Interactive API Explorer:** https://api-dev.episciences.org:8443/api
   - **Main Application:** https://api-dev.episciences.org:8443/

### Development Environment

#### Available Make Commands

Run `make help` to see all available commands. Key commands for development:

```bash
# Environment Management
make docker-up         # Start all containers (auto-generates SSL certificates)
make docker-down       # Stop all containers
make docker-restart    # Restart all containers
make docker-status     # Check container status
make docker-logs       # Follow container logs

# Development Tools
make docker-shell      # Enter PHP container for debugging
make docker-mysql      # Access MySQL database
make docker-test       # Run PHPUnit tests in container

# SSL Certificate Management
make ssl-certs         # Generate SSL certificates manually
make ssl-clean         # Remove SSL certificates
```

#### HTTPS Development Setup

The development environment uses HTTPS with a local domain for production-like conditions:

- **Domain:** `api-dev.episciences.org`
- **HTTPS Port:** `8443`
- **HTTP Port:** `8080` (redirects to HTTPS)

**Important:** You'll see a browser security warning because we use self-signed certificates for development. This is normal - click "Advanced" → "Proceed to api-dev.episciences.org" to continue.

#### SSL Certificate Management

SSL certificates are automatically generated when you run `make docker-up`. Key points:

- Certificates are **not committed** to the repository for security
- Each developer generates their own certificates locally
- Certificates are valid for 1 year and include proper server authentication extensions
- Use `make ssl-clean ssl-certs` to regenerate certificates if needed

### Testing & Development Workflow

#### Running Tests

```bash
# Run tests in Docker container (recommended)
make docker-test

# Run tests locally (requires local PHP 8.2+ setup)
make test
make test-unit          # Unit tests only
make test-coverage      # Tests with coverage report
```

#### Database Access

```bash
# Access MySQL via container
make docker-mysql

# Or connect with external tools using:
# Host: localhost
# Port: 3306
# User: root
# Password: root
# Database: episciences
```

#### Development URLs

- **API Documentation:** https://api-dev.episciences.org:8443/api/docs
- **OpenAPI Spec:** https://api-dev.episciences.org:8443/api/docs.json
- **Symfony Profiler:** https://api-dev.episciences.org:8443/_profiler

#### Troubleshooting

**Common Issues:**

1. **"Connection refused" error:** Check if containers are running with `make docker-status`

2. **"SSL certificate error":** This is expected for self-signed certificates - proceed past the browser warning

3. **"Port already in use":** Stop existing containers with `make docker-down` or check for conflicting services on ports 8080, 8443, 3306

4. **Permission issues:** Ensure Docker has proper permissions and your user is in the docker group

5. **SSL certificate issues:** Regenerate certificates with `make ssl-clean ssl-certs`

**Getting Help:**

- Run `make help` to see all available commands
- Check container logs with `make docker-logs`  
- Verify setup with `make check-prereqs`
