<?php

namespace PHPFirewall;

use PHPFirewall\Base;
use PHPFirewall\PackagesData;
use ReflectionClass;
use SleekDB\Store;

class Auth
{
    protected $terminal;

    protected $authStore;

    public function __construct($terminal)
    {
        $this->terminal = $terminal;

        $this->authStore = new Store("auth", $this->terminal->databaseDirectory, $this->terminal->storeConfiguration);

        // $this->authStore->updateOrInsert(
        //     [
        //         '_id'       => 1,
        //         'username'  => 'fw',
        //         'password'  => password_hash('123fw123', PASSWORD_BCRYPT, ['cost' => 4])
        //     ]
        // );
    }

    public function newAccount()
    {
        //
    }

    public function attempt($username, $password)
    {
        return $this->checkAccount($username, $password);
    }

    protected function checkAccount($username, $password)
    {
        $account = $this->authStore->findBy(['username', '=', strtolower($username)]);

        if (count($account) === 1) {
            if ($this->checkPassword($password, $account[0]['password'])) {
                if ($this->passwordNeedsRehash($account[0]['password'])) {
                    $account[0]['password'] = $this->hashPassword($password);

                    $this->authStore->update($account);
                }

                return true;
            }
        }

        $this->hashPassword(rand());

        return false;
    }

    public function changePassword()
    {
        //
    }

    protected function hashPassword(string $password, int $workFactor = 4)
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $workFactor]);
    }

    protected function checkPassword(string $password, string $hashedPassword)
    {
        return password_verify($password, $hashedPassword);
    }

    protected function passwordNeedsRehash(string $hashedPassword, int $workFactor = 4)
    {
        return password_needs_rehash(
            $hashedPassword,
            PASSWORD_BCRYPT,
            [
                'cost' => $workFactor
            ]
        );
    }
}