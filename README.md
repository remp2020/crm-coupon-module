# CRM Coupon Module

[![Translation status @ Weblate](https://hosted.weblate.org/widgets/remp-crm/-/coupon-module/svg-badge.svg)](https://hosted.weblate.org/projects/remp-crm/coupon-module/)

## API documentation

All examples use `http://crm.press` as a base domain. Please change the host to the one you use
before executing the examples.

All examples use `XXX` as a default value for authorization token, please replace it with the
real tokens:

* *API tokens.* Standard API keys for server-server communication. It identifies the calling application as a whole.
They can be generated in CRM Admin (`/api/api-tokens-admin/`) and each API key has to be whitelisted to access
specific API endpoints. By default the API key has access to no endpoint. 
* *User tokens.* Generated for each user during the login process, token identify single user when communicating between
different parts of the system. The token can be read:
    * From `n_token` cookie if the user was logged in via CRM.
    * From the response of [`/api/v1/users/login` endpoint](https://github.com/remp2020/crm-users-module#post-apiv1userslogin) -
    you're free to store the response into your own cookie/local storage/session.

API responses can contain following HTTP codes:

| Value | Description |
| --- | --- |
| 200 OK | Successful response, default value | 
| 400 Bad Request | Invalid request (missing required parameters) | 
| 403 Forbidden | The authorization failed (provided token was not valid) | 
| 404 Not found | Referenced resource wasn't found | 

If possible, the response includes `application/json` encoded payload with message explaining
the error further.


#### POST `/api/v1/coupon/activate`

Activate coupon specified by code for authenticated user.


##### *Headers:*

| Name | Value | Required | Description |
| --- | --- | --- | --- |
| Authorization | Bearer *String* | yes | User token. |

##### *Params:*

| Name | Value | Required | Description |
| --- |---| --- | --- |
| code | *String* | yes | The code of coupon to activate.|
| notifyUser | *Boolean* | no | Flag indicating that user should be notified (email, push notification).|

##### *Example:*

```shell
curl -X POST \
  http://crm.press/api/v1/coupon/activate \
  -H 'Authorization: Bearer XXX' \
  -H 'Content-Type: application/json' \
  -d '{
    "code": "123456-789ABC-DEFG",
    "notifyUser": true
  }'
```

Response:

```json5
{
    "coupon_id": 1234567,
    "coupon_type": "new-user-promo",
    "subscription_id": 1234345,
    "subscription_type_id": 123,
    "subscription_type_name": "1 month promo subscription",
    "subscription_start_time": "2020-07-02T11:30:00+00:00", // String; RFC3339 encoded start time
    "subscription_end_time": "2020-08-02T11:30:00+00:00" // String; RFC3339 encoded end time
}
```