<?php
require_once("../examples/SplClassLoader.php");

function exception_error_handler($errno, $errstr, $errfile, $errline)
{
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

set_error_handler("exception_error_handler");

$classLoader = new SplClassLoader('DALTCORE\Plesk', '../src');
$classLoader->register();

/*
 * Utility functions
 */

function random_string($length = 8)
{
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    return substr(str_shuffle($chars), 0, $length);
}

/*
 * This file attempts the following operations on a real Plesk server (intended to be run by an admin account)
 *
 * 1.  Get server information (GetServerInfo)
 * 2.  Find shared ip address (ListIPAddresses)
 * 3.  Find unlimited service plan (ListServicePlans)
 * 4.  Creates a new client (CreateClient)
 * 5.  Get new client from server (GetClient)
 * 6.  Update client information (UpdateClient)
 * 7.  Create subscription (CreateSubscription)
 * 8.  List subscriptions (ListSubscriptions)
 * 9.  Create new site (CreateSite)
 * 10. Find created site (ListSite)
 * 11. Update site information (UpdateSite)
 * 12. Create email address (CreateEmailAddress)
 * 13. List email addresses (ListEmailAddresses)
 * 14. Update email address (UpdateEmailAddress)
 * 15. Delete email address (DeleteEmailAddress)
 * 16. Create site alias (CreateSiteAlias)
 * 17. List site aliases (ListSiteAliases)
 * 18. Delete site alias (DeleteEmailAddress)
 * 19. Create subdomain (CreateSubdomain)
 * 20. List subdomains (ListSubdomains)
 * 21. Update subdomain(UpdateSubdomain)
 * 22. Rename subdomain(RenameSubdomain)
 * 23. Delete subdomain(DeleteSubdomain)
 * 24. List database servers (ListDatabaseServers)
 * 25. Create database (CreateDatabase)
 * 26. List databases (ListDatabases)
 * 27. Create database user (CreateDatabaseUser)
 * 28. Get database user info (GetDatabaseUser)
 * 29. Delete database (DeleteDatabase)
 * 30. Delete previously created site (DeleteSite)
 * 31. Delete previously created subscription (DeleteSubscription)
 * 32. Deletes previously created client (DeleteClient)
 *
 */

$config = array(
    'host'=>'example.com',
    'username'=>'username',
    'password'=>'password',
);


/*
* Choose which tests to run, try not to do them all at the same time, it can be quite slow
*/
$runSiteTests = true;
$runEmailAddressTests = true;
$runSiteAliasTests = true;
$runSubdomainTests = true;
$runDatabaseTests = true;

$data = array();

/*
 * 1. Get server information (GetServerInfo)
 */

$request = new \DALTCORE\Plesk\GetServerInfo($config);
$info = $request->process();

echo "Running test suite on " . $info['server_name'] . PHP_EOL;


/*
 * 2. Find shared ip address (ListIPAddresses)
 */

$request = new \DALTCORE\Plesk\ListIPAddresses($config);
$ips = $request->process();

foreach ($ips as $ip) {
    if ($ip['is_default']) {
        $data['shared_ip_address'] = $ip['ip_address'];
    }
}

if (!isset($data['shared_ip_address'])) {
    throw new Exception("Couldn't find any shared IP addresses");
}

echo "Shared IP Address found: " . $data['shared_ip_address'] . PHP_EOL;

/*
 * 3.  Find unlimited service plan (ListServicePlans)
 */

$request = new \DALTCORE\Plesk\ListServicePlans($config);
$plans = $request->process();

foreach ($plans as $plan) {
    if (strtolower($plan['name']) == 'unlimited') {
        $data['unlimited_plan_id'] = $plan['id'];
        echo "Unlimited Service Plan found: " . $data['unlimited_plan_id'] . PHP_EOL;
        break;
    }
}

if (!isset($data['unlimited_plan_id'])) {
    throw new Exception("Couldn't find unlimited service plan");
}

/*
 * 4. Creates a new client (CreateClient)
 */

$data['client_username'] = strtolower(random_string());

$request = new \DALTCORE\Plesk\CreateClient($config, array(
    'contact_name' => random_string(),
    'username' => $data['client_username'],
    'password' => random_string(16) . "1!",
));

$request->process();
$data['client_id'] = $request->id;
echo "Client created: " . $data['client_username'] . PHP_EOL;


try {
    /*
     * 5. Get new client from server (GetClient)
     */

    $request = new \DALTCORE\Plesk\GetClient($config, array(
        'username' => $data['client_username'],
    ));
    $info = $request->process();
    echo "Client found: " . $data['client_username'] . PHP_EOL;

    /*
     * 6. Update client information (UpdateClient)
     */

    $request = new \DALTCORE\Plesk\UpdateClient($config, array(
        'username' => $data['client_username'],
        'phone' => random_string(),
        'email' => random_string() . '@example.com',
    ));
    $info = $request->process();
    echo "Client updated: " . $data['client_username'] . PHP_EOL;


    /*
     * 7.  Create subscription (CreateSubscription)
     */

    $params = array(
        'domain_name' => random_string() . '.com',
        'username' => $data['client_username'],
        'password' => random_string(16) . '1!',
        'ip_address' => $data['shared_ip_address'],
        'owner_id' => $data['client_id'],
        'service_plan_id' => $data['unlimited_plan_id'],
    );

    $request = new \DALTCORE\Plesk\CreateSubscription($config, $params);
    $request->process();
    $data['subscription_id'] = $request->id;
    echo "Subscription created: " . $data['subscription_id'] . PHP_EOL;


    /*
     * 8.  List subscriptions (ListSubscriptions)
     */

    $request = new \DALTCORE\Plesk\ListSubscriptions($config);
    $subscriptions = $request->process();

    $subscription_found = false;
    foreach ($subscriptions as $subscription) {
        if ($subscription['id'] == $data['subscription_id']) {
            $subscription_found = true;
        }
    }

    if (!$subscription_found) {
        throw new Exception("Couldn't find created subscription");
    }

    echo "Subscription found: " . $data['subscription_id'] . PHP_EOL;

    if ($runSiteTests) {
        /*
         * 9.  Create new site (CreateSite)
         */

        $data['domain'] = random_string() . '.com';
        $request = new \DALTCORE\Plesk\CreateSite($config, array(
            'domain' => $data['domain'],
            'subscription_id' => $data['subscription_id'],
        ));
        $info = $request->process();
        $data['site_id'] = $request->id;
        echo "Site created: " . $data['domain'] . PHP_EOL;


        /*
         * 10. Find created site (ListSite)
         */

        $request = new \DALTCORE\Plesk\ListSites($config, array(
            'subscription_id' => $data['subscription_id'],
        ));
        $sites = $request->process();

        $site_found = false;
        foreach ($sites as $site) {
            if ($site['id'] == $data['site_id']) {
                $site_found = true;
            }
        }

        if (!$site_found) {
            throw new Exception("Couldn't find created site");
        }

        echo "Site found: " . $data['domain'] . PHP_EOL;


        /*
         * 11. Update site information (UpdateSite)
         */

        $data['domain'] = random_string() . '.com';
        $request = new \DALTCORE\Plesk\UpdateSite($config, array(
            'id' => $data['site_id'],
            'domain' => $data['domain'],
        ));
        $info = $request->process();
        echo "Site updated: " . $data['domain'] . PHP_EOL;
    }

    if ($runSiteTests && $runEmailAddressTests) {
        /*
         * 12. Create email address (CreateEmailAddress)
         */

        $data['email_address'] = random_string(4) . '@' . $data['domain'];
        $request = new \DALTCORE\Plesk\CreateEmailAddress($config, array(
            'email' => $data['email_address'],
            'password' => random_string() . "1!",
        ));
        $info = $request->process();
        $data['email_address_id'] = $request->id;
        echo "Email address created: " . $data['email_address'] . PHP_EOL;

        /*
         * 13. List email addresses (ListEmailAddresses)
         */
        $request = new \DALTCORE\Plesk\ListEmailAddresses($config, array(
            'site_id' => $data['site_id'],
        ));
        $email_addresses = $request->process();

        $email_address_found = false;
        foreach ($email_addresses as $email_address) {
            if ($email_address['id'] == $data['email_address_id']) {
                $email_address_found = true;
            }
        }

        if (!$email_address_found) {
            throw new Exception("Couldn't find created email address (" . $data['email_address_id'] . ")");
        }

        echo "Email address found: " . $data['email_address'] . PHP_EOL;


        /*
         * 14. Update email address (UpdateEmailAddress)
         */

        $request = new \DALTCORE\Plesk\UpdateEmailPassword($config, array(
            'email' => $data['email_address'],
            'password' => random_string(),
        ));
        $info = $request->process();
        echo "Email address password changed: " . $data['email_address'] . PHP_EOL;


        /*
         * 15. Delete email address (DeleteEmailAddress)
         */

        $request = new \DALTCORE\Plesk\DeleteEmailAddress($config, array(
            'email' => $data['email_address'],
        ));
        $info = $request->process();
        echo "Email address deleted: " . $data['email_address'] . PHP_EOL;
    }

    if ($runSiteTests && $runSiteAliasTests) {
        /*
         * 16. Create site alias (CreateSiteAlias)
         */

        $data['site_alias'] = random_string() . '.' . $data['domain'];
        $params = array(
            'site_id' => $data['site_id'],
            'alias' => $data['site_alias'],
        );

        $request = new \DALTCORE\Plesk\CreateSiteAlias($config, $params);
        $info = $request->process();
        $data['site_alias_id'] = $request->id;
        echo "Site alias created: " . $data['site_alias'] . PHP_EOL;


        /*
         * 17. List site aliases (ListSiteAliases)
         */

        $request = new \DALTCORE\Plesk\ListSiteAliases($config, array(
            'site_id' => $data['site_id'],
        ));
        $aliases = $request->process();

        $alias_found = false;
        foreach ($aliases as $alias_id => $alias_name) {
            if ($alias_id == $data['site_alias_id']) {
                $alias_found = true;
            }
        }

        if (!$alias_found) {
            throw new Exception("Couldn't find created site alias");
        }

        echo "Site alias found: " . $data['site_alias'] . PHP_EOL;

        /*
         * 18. Delete site alias (DeleteEmailAddress)
         */

        $request = new \DALTCORE\Plesk\DeleteSiteAlias($config, array(
            'id' => $data['site_alias_id'],
        ));
        $info = $request->process();
        echo "Site alias deleted: " . $data['site_alias'] . PHP_EOL;
    }

    if ($runSiteTests && $runSubdomainTests) {
        /*
         * 19. Create subdomain (CreateSubdomain)
         */

        $data['subdomain'] = random_string();
        $request = new \DALTCORE\Plesk\CreateSubdomain($config, array(
            'domain' => $data['domain'],
            'subdomain' => $data['subdomain'],
            'www_root' => '/subdomains/' . strtolower($data['subdomain']),
            'fpt_username' => random_string(),
            'fpt_password' => random_string(),
        ));
        $info = $request->process();
        $data['subdomain_id'] = $request->id;
        echo "Subdomain created: " . $data['subdomain'] . PHP_EOL;


        /*
         * 20. List subdomains (ListSubdomains)
         */

        $request = new \DALTCORE\Plesk\ListSubdomains($config, array(
            'site_id' => $data['site_id'],
        ));
        $subdomains = $request->process();

        $subdomain_found = false;
        foreach ($subdomains as $subdomain) {
            if ($subdomain['id'] == $data['subdomain_id']) {
                $subdomain_found = true;
            }
        }

        if (!$subdomain_found) {
            throw new Exception("Couldn't find created subdomain");
        }

        echo "Subdomain found: " . $data['subdomain'] . PHP_EOL;


        /*
         * 21. Update subdomain(UpdateSubdomain)
         */

        $request = new \DALTCORE\Plesk\UpdateSubdomain($config, array(
            'id' => $data['subdomain_id'],
            'www_root' => '/subdomains/' . strtolower($data['subdomain']) . '2',
        ));
        $info = $request->process();
        echo "Subdomain updated: " . $data['subdomain'] . PHP_EOL;


        /*
         * 22. Rename subdomain(RenameSubdomain)
         */

        $data['subdomain'] = random_string();
        $request = new \DALTCORE\Plesk\RenameSubdomain($config, array(
            'id' => $data['subdomain_id'],
            'name' => $data['subdomain'],
        ));
        $info = $request->process();
        echo "Subdomain renamed: " . $data['subdomain'] . PHP_EOL;


        /*
         * 23. Delete subdomain(DeleteSubdomain)
         */

        $request = new \DALTCORE\Plesk\DeleteSubdomain($config, array(
            'id' => $data['subdomain_id'],
        ));
        $info = $request->process();
        echo "Subdomain deleted: " . $data['subdomain'] . PHP_EOL;
    }

    if ($runDatabaseTests) {
        /*
        * 24. List database servers (ListDatabaseServers)
        */

        $request = new \DALTCORE\Plesk\ListDatabaseServers($config);
        $servers = $request->process();

        $server_found = false;
        foreach ($servers as $server) {
            if ($server['type'] == 'mysql') {
                $data['db_server_id'] = $server['id'];
                $server_found = true;
            }
        }

        if (!$server_found) {
            throw new Exception("Couldn't find mysql database server");
        }

        echo "Database server found: " . $data['db_server_id'] . PHP_EOL;


        /*
        * 25. Create database (CreateDatabase)
        */

        $request = new \DALTCORE\Plesk\CreateDatabase($config, array(
            'name' => random_string(),
            'subscription_id' => $data['subscription_id'],
            'server_id' => $data['db_server_id'],
            'type' => 'mysql',
        ));
        $info = $request->process();
        $data['db_id'] = $request->id;

        echo "Database created: " . $data['db_id'] . PHP_EOL;


        /*
        * 26. List databases (ListDatabases)
        */

        $request = new \DALTCORE\Plesk\ListDatabases($config, array(
            'subscription_id' => $data['subscription_id'],
        ));
        $databases = $request->process();

        $database_found = false;
        foreach ($databases as $database) {
            if ($database['id'] == $data['db_id']) {
                $database_found = true;
            }
        }

        if (!$database_found) {
            throw new Exception("Couldn't find created database");
        }

        echo "Database found: " . $data['db_id'] . PHP_EOL;


        /*
        * 27. Create database user (CreateDatabaseUser)
        */

        $data['db_user_username'] = random_string();
        $request = new \DALTCORE\Plesk\CreateDatabaseUser($config, array(
            'database_id' => $data['db_id'],
            'username' => $data['db_user_username'],
            'password' => random_string(),
        ));
        $info = $request->process();
        $data['db_user_id'] = $request->id;

        echo "Database user created: " . $data['db_user_id'] . PHP_EOL;


        /*
        * 28. Get database user info (GetDatabaseUser)
        */

        $request = new \DALTCORE\Plesk\GetDatabaseUser($config, array(
            'database_id' => $data['db_id'],
        ));
        $info = $request->process();

        if ($data['db_user_id'] != $request->id) {
            throw new Exception("Created database user doesn't match retrieved database user");
        }

        echo "Database user found: " . $data['db_user_id'] . PHP_EOL;


        /*
        * 29. Delete database (DeleteDatabase)
        */

        /*$request = new \DALTCORE\Plesk\DeleteDatabase($config, array(
            'id'=>$data['db_id'],
        ));
        $info = $request->process();
        echo "Database deleted: ".$data['db_id'].PHP_EOL;*/
    }

    if ($runSiteTests) {
        /*
         * 30. Delete site (DeleteSite)
         */

        $request = new \DALTCORE\Plesk\DeleteSite($config, array(
            'id' => $data['site_id'],
        ));
        $info = $request->process();
        echo "Site deleted: " . $data['domain'] . PHP_EOL;
    }

    /*
     * 31. Deletes previously created subscription (DeleteSubscription)
     */

    $request = new \DALTCORE\Plesk\DeleteSubscription($config, array(
        'id' => $data['subscription_id'],
    ));
    $info = $request->process();
    echo "Subscription deleted: " . $data['subscription_id'] . PHP_EOL;

    /**
     * 32. Creates a secret key
     */

    $request = new \DALTCORE\Plesk\CreateSecretKey($config, [
        'ip_address' => file_get_contents('https://api.ipify.org'),
    ]);
    $info = $request->process();
    $data['secret_key'] = $request->key;
    echo "Secret Key created: " . $request->key . PHP_EOL;

    /**
     * 33. Lists secret keys (login with secret key)
     */

    $request = new \DALTCORE\Plesk\ListSecretKeys(['key' => $data['secret_key'], 'host' => $config['host']]);
    $keys = $request->process();

    $secret_key_found = false;
    foreach ($keys as $key) {
        if ($key['key'] == $data['secret_key']) {
            $secret_key_found = true;
        }
    }

    if (!$secret_key_found) {
        throw new Exception("Couldn't find created secret_key");
    }

    /**
     * 34. Delete secret key
     */

    $request = new \DALTCORE\Plesk\DeleteSecretKey($config, [
        'key' => $data['secret_key'],
    ]);
    $request->process();

    $request = new \DALTCORE\Plesk\ListSecretKeys($config);
    $keys = $request->process();

    $secret_key_found = false;
    foreach ($keys as $key) {
        if ($key['key'] == $data['secret_key']) {
            $secret_key_found = true;
        }
    }

    if ($secret_key_found) {
        throw new Exception("Failed to delete secret_key");
    }

} catch (Exception $e) {
    throw $e;
} finally {
    /*
     * 32. Deletes previously created client (DeleteClient)
     */

    $request = new \DALTCORE\Plesk\DeleteClient($config, array(
        'id' => $data['client_id'],
    ));
    $request->process();
    echo "Client deleted: " . $data['client_id'] . PHP_EOL;
}
