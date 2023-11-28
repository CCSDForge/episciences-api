# episciences-api
## About REST Episciences API

The REST Episciences API can be used with **authentication and authorisation**.

Authentication is reserved exclusively for users of one of the Episciences journals, so, it is vital to recover
your **API password** - once you have logged on to the journal site - via **My space > Reset my API password**.

### Get the authentication token with a POST request

#### The API is called up securely via a JWT token, which can be retrieved via "/api/login" endpoint: 

```
curl -X 'POST' \
'https://api-preprod.episciences.org/api/login' \
-H 'accept: application/json' \
-H 'Content-Type: application/json' \
-d '{
"username": "login",
"password": "api pwd",
"code": "journal’s name"
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
