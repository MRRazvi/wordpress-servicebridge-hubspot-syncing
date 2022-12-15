# ServiceBridge HubSpot Syncing

Providing syncing from [ServiceBridge](https://cloud.servicebridge.com) to [HubSpot](https://app.hubspot.com).

## Program Flow

1. First fetch the data from service bridge api
2. Than store that data into database
3. Filter the data check what sync already or what not
4. Not synced data feed to hub spot api

## Setup

```
git clone https://github.com/MRRazvi/servicebridge-hubspot-syncing.git
cd servicebridge-hubspot-syncing
composer install
php artisan migrate:fresh
php sb:accounts
php hs:owners
php sb:database
php hs:sync
```

### Author

Mubashir Rasool Razvi
[Upwork](https://www.upwork.com/freelancers/mrrazvi)
[LinkedIn](https://www.linkedin.com/in/mrrazvi)
