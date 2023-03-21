# ServiceBridge HubSpot Syncing

Providing syncing from [ServiceBridge](https://cloud.servicebridge.com) to [HubSpot](https://app.hubspot.com).

# Prerequisites

-   PHP 8.1
-   MySQL
-   Service Bridge Account (user id, user pass)
-   HubSpot Account (api key)

## Program Flow

We have 4 bots to do multiple things, and we will discuss them one by one.

**sb:accounts**

-   it will pick array of all the provided accounts
-   insert into database for latter use

**hs:owners**

-   will go to hubspot api and pick all the owners
-   insert into database for latter use

**sb:sync**

-   will pick all SB accounts from database
-   loop through accounts one by one
    -   pick all the estimates from SB api
        -   check either we need to create or update the record in database
        -   store estimate id, customer id, email, status, version, finish date, created and updated at
        -   => for **synced** column check if there is change in version than the value will be false
        -   => **scheduled_at** column value coming from finish date of estimate if not exist than won or lost date we will pick
    -   pick all the work orders from SB api
        -   do same as for estimates just estimate_id will replace work_order_id

**hs:sync**

-   sync estimates will run first
    -   pick all the estimates from database where **synced is false** and tries are less than 3
        -   go on estimate one by one
            -   increment the try
            -   get latest estimate data via **estimate_id** from database
            -   check the status if not from our list (Finished, WonEstimate, LostEstimate, OpenEstimate) skip it otherwise move on
            -   get customer data from HS api
            -   get contact data from HS api
            -   get service location from HS api
            -   find latest job on that customer
                -   pick all the estimates from database
                -   pick all the work order from database
                -   compare them on the basis of **scheduled_at**
            -   create or update contact on HS api
            -   search for deal
            -   if we have deal than create or update it
    -   same process for work orders but will skip the deal part

## Setup

```
git clone https://github.com/MRRazvi/servicebridge-hubspot-syncing.git
cd servicebridge-hubspot-syncing
mv .env.example .env
php artisan migrate:fresh
php artisan sb:accounts
php artisan hs:owners
php artisan sb:sync
php artisan hs:sync
```

-   In **.env** file you need to configure HubSpot api key

-   In **app/Console/Commands/ServiceBridgeAccountsCommand.php** you need to configure array with your SB accounts
    composer install.

## Logs

You can see logs under **storage/logs**, individual for every bot.

### Author

Mubashir Rasool Razvi  
[Upwork](https://www.upwork.com/freelancers/mrrazvi)  
[LinkedIn](https://www.linkedin.com/in/mrrazvi)
