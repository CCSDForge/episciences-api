# episciences-api
## About REST Episciences API

The REST Episciences API can be used with **authentication and authorisation**.

Authentication is reserved exclusively for users of one of the Episciences journals, so, it is vital to recover
your **API password** - once you have logged on to the journal site - via **My space > Reset my API password**.

### Get the authentication token with a POST request

#### The API is called up securely via a JWT token, which can be retrieved via "/api/login" endpoint:  

curl -X 'POST' \
'https://serverName/login' \
-H 'accept: application/json' \
-H 'Content-Type: application/json' \
-d '{
"username": "login",
"password": "api pwd",
"code": "journal’s name"
}'

#### Response type:

{"token":"eyJ0eXAiOiJKV1….", refresh_token":"3ff818151dd…"}

The token expires after **one hour**.
The refresh_token expires after **one month**.

It is possible to generate a new token without asking the user to enter these identifiers via "/token/refresh":

curl -X 'POST' \
'https://serverName/api/token/refresh' \
-H 'accept: application/json' \
-H 'Content-Type: application/json' \
-d '{
"refresh_token": "3ff818151dd…"
}'


#### To check that you're connected and as an example of how to use it: 

curl -X 'GET' \
'https://serverName/api/me' \
-H 'accept: application/ld+json' \
-H 'Authorization: Bearer
'eyJ0eXAiOiJKV1….'

**To explore the available endpoints: https://api-preprod.episciences.org/docs**