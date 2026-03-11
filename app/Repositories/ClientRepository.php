<?php

namespace App\Repositories;

use App\Exceptions\CustomModelNotFoundException;
use App\Models\Client;

class ClientRepository
{
    public function find()
    {
    }

    public function store($data)
    {
        return Client::create($data);
    }

    public function update($id, $data)
    {
        $client = Client::whereUuid($id)->first();

        if (blank($client)) {
            throw new CustomModelNotFoundException("No client found with Id $id");
        }

        $client->update($data);

        return $client->refresh();
    }
}
