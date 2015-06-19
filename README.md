Cryptocurrency-faucet-script
============================
* A big THANK YOU goes to a friend who provided the script and requested to not be named.
* We would love it if you let us know that you are using the script so we can showcase your website on bitcoinproject.net

DEMO: http://wow.bitcoinproject.net/

For those who are looking for a faucet script that supports multiple coins:
http://cryptocurrency-scripts.com/index.php?page=item&id=10

Faucet features:

- Can be used for the most cryptocurrencys
- Minimum and maximum payouts can be set
- Payment system for staged,timed or direct payouts
- Recaptcha integrated (http://www.google.com/recaptcha/) IMPORTANT: Please note that Recaptcha2 can be set within the config file. Version 2 will be released shortly directly from google and is already implimented within the script.
- Proxies filter option
- Promocodes possible for extra payouts
- Useable with encrypted wallets
- Easy editable template system


Installation:

1. Download or clone this repository
2. Upload the files to your ftp folder
3. Create a database and import faucet.sql
4. Open the config.php and edit all the settings within to suit your needs - This could take some time :)
5. Create cronjob(s):

If you set "stage_payments" => true and "staged_payment_cron_only" => true (you did that on step 4), you will need to create a cronjob for /cron/run.php and /lib/proxy_filter/cron/tor.php

If you set "stage_payments" => true and "staged_payment_cron_only" => false you just have to create a cronjob for /lib/proxy_filter/cron/tor.php

// IMPORTANT: The tor proxy list gets downloaded from https://www.dan.me.uk/torlist/ - He has only given permission to download once every hour! Please note that you will be banned from the service if you exceed this quota! Create a .htaccess and .htpasswd for the cronjob folder and /lib/proxy_filter/cron/, so only you can fire them! //


How to add promo codes:

Go to your database and find the "sf_promo_codes table". Add a new line and set your code, minimum_payout, maximum_payout and uses.

- uses = 0 // Promo code disabled
- uses = -1 // No limit on using this code
- uses = 50 // The code counts down until 0 and works e.g. 50 times



Feel free to donate :)
- BTC: 1Av1sFhiWVzhcgxPMsZXni4q5fYCMtR2jE
- DOGE: DLoveeefqGPuEqZvc7PXNt2mvu36YPXcGb
