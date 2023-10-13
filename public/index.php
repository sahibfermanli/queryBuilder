<?php

use App\Services\DB;

require_once('../env_loader.php');
require_once('../autoloader.php');

$users = DB::query()
    ->table('users')
    ->join('stores', function (DB $join) {
        $join->on('users.store_id', '=', 'stores.id');
        $join->where('stores.title', 'like', '%a%');
        $join->orWhere('stores.id', 3);
    })
    ->leftJoin('users as created_user', 'users.created_by', '=', 'created_user.id')
    ->select(['users.id', 'users.name', 'users.phone', 'stores.title as store', 'created_user.name as created_user_name'])
    ->where('users.id', '<', 10)
    ->where(function (DB $query) {
        $query->where('users.name', 'sahib');
        $query->where(function (DB $q) {
            $q->where('users.name', '=','sahib2');
            $q->orWhereNull('users.phone');
        });
    })
    ->orWhere(function (DB $query) {
        $query->orWhere('users.name', 'sahib3');
        $query->orWhere('users.name', 'nicat');
    })
    ->limit(3)
    ->get();

print_r($users);
