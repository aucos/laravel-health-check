<?php

namespace Aucos\HealthCheck\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use LdapRecord\Connection;
use LdapRecord\Container;

class HealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Health check of different external services';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->checkMainDatabase();
        $this->checkLdapConnection();
        $this->checkMailConnection();

        if ($this->hasOracleConfiguration()) {
            $this->checkOracleDatabaseConnection();
        } else {
            $this->line('Skipping Oracle database connection check: Configuration not found.');
        }

        $this->checkQueueConnection();

        $this->info('Health check completed.');

        return Command::SUCCESS;

    }

    private function checkMainDatabase()
    {
        $this->line('Testing main database connection (mysql)...');

        try {
            $databaseName = config('database.connections.mysql.database');
            DB::connection('mysql')->select('SELECT 1');

            $this->info("✅ Main database connection successful (Database: $databaseName).");
        } catch (Exception $e) {
            $this->error('❌ Main database connection failed:');
            $this->error($e->getMessage());
        }
    }

    private function checkLdapConnection()
    {
        $this->line('Testing LDAP connection...');

        try {
            $connection = new Connection([
                'hosts' => config('ldap.connections.default.hosts'),
                'port' => config('ldap.connections.default.port', 389),
                'base_dn' => config('ldap.connections.default.base_dn'),
                'username' => config('ldap.connections.default.username'),
                'password' => config('ldap.connections.default.password'),
                'timeout' => config('ldap.connections.default.timeout', 5),
                'use_ssl' => config('ldap.connections.default.use_ssl', false),
                'use_tls' => config('ldap.connections.default.use_tls', false),
            ]);

            Container::addConnection($connection);

            $connection->connect();

            $this->info('✅ LDAP connection successful (Host: '.implode(', ', config('ldap.connections.default.hosts')).', Base DN: '.config('ldap.connections.default.base_dn').').');
        } catch (Exception $e) {
            $this->error('❌ LDAP connection failed:');
            $this->error($e->getMessage());
        }
    }

    private function checkMailConnection()
    {
        $this->line('Testing Mail connection...');

        try {
            $testMail = config('health-check.testmail');

            if (empty($testMail)) {
                $this->error('❌ The environment variable APP_TESTMAIL is not set.');
                return;
            }

            Mail::raw('Health check test email', function ($message) use ($testMail) {
                $message->to($testMail)
                    ->subject('Health Check');
            });

            $this->info("✅ Mail connection successful. Test email sent to $testMail");
        } catch (Exception $e) {
            $this->error('❌ Mail connection failed:');
            $this->error($e->getMessage());
        }
    }

    private function hasOracleConfiguration(): bool
    {
        $config = config('database.connections.oracle');
        return isset($config['host'], $config['database'], $config['username'], $config['password']);
    }

    private function checkOracleDatabaseConnection()
    {
        $this->line('Testing Oracle database connection...');

        try {
            $databaseName = config('database.connections.oracle.database');
            DB::connection('oracle')->select('SELECT 1 FROM DUAL');

            $this->info("✅ Oracle database connection successful (Database: $databaseName).");
        } catch (Exception $e) {
            $this->error('❌ Oracle database connection failed:');
            $this->error($e->getMessage());
        }
    }

    private function checkQueueConnection()
    {
        $this->line('Testing Queue connection...');

        try {
            $queueConnection = config('queue.default');
            Queue::push(function () {
                // No operation, just a test
            });

            $this->info("✅ Queue connection successful (Connection: $queueConnection). Test job pushed successfully.");
        } catch (Exception $e) {
            $this->error('❌ Queue connection failed:');
            $this->error($e->getMessage());
        }
    }
}
