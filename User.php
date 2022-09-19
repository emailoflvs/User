<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\{DB, Hash, Mail, Validator};
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'login',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];


    public function setPasswordAttribute(string $password)
    {
        $this->attributes['password'] = Hash::make($password);
    }


    /**
     * Complete the users update
     *
     * @param array $users
     *
     * @return Collection
     * @throws ValidationException|\Throwable
     */
    public static function updateUsers(array $usersData = []): Collection
    {
        $usersData = collect($usersData);
        // adding unique validation rules with ignoring by the correspond id
        $emailUniqueRules = $usersData->mapWithKeys(function ($userData, $index){
            return [$index.'.email' => 'unique:App\Models\User,email'
                . (isset($userData['id']) ? ",{$userData['id']}" : '')];
        })->toArray();
        $loginRules = $usersData->mapWithKeys(function ($userData, $index){
            return [$index.'.login' => 'unique:App\Models\User,login'
                . (isset($userData['id']) ? ",{$userData['id']}" : '')];
        })->toArray();

        $rules = [
                '*.id' => ['bail', 'required', 'regex:/(^[0-9]+$)/u', 'exists:App\Models\User,id', 'distinct'],
                '*.name' => ['regex:/(^[a-zA-Z\s]+$)/u', 'max:100', 'min:10'],
                '*.login' => ['regex:/(^[a-zA-Z\s]+$)/u', 'max:100', 'distinct'],
                '*.password' => ['string', 'min:8'],
                '*.email' => ['email:rfc,dns', 'distinct']
            ] + $emailUniqueRules + $loginRules;

        /** @var Validator $validator */
        $usersData = Validator::make($usersData->toArray(), $rules)->validate();

        DB::beginTransaction();
        try {
            $users = collect($usersData)->map(function(array $userData): User {
                $user = User::findOrFail($userData['id']);
                $user->fill($userData);
                $user->save();

                return $user;
            });
        } catch (\Throwable $e) {
            DB::rollBack();
            //TODO: handle the exception on controller
            throw $e;
        }
        DB::commit();

        return $users;
    }


    /**
     * Complete the users store
     *
     * @param array
     *
     * @return Collection
     * @throws ValidationException|\Throwable
     */
    public static function storeUsers($users = null): Collection
    {
        /** @var Validator $validator */
        $users = Validator::make($users, [
            '*.name' => ['required', 'regex:/(^[a-zA-Z\s]+$)/u', 'max:100', 'min:10'],
            '*.login' => ['required', 'regex:/(^[a-zA-Z\s]+$)/u', 'max:100', 'unique:App\Models\User,login', 'distinct'],
            '*.password' => ['required', 'string', 'min:8'],
            '*.email' => 'email:rfc,dns|unique:App\Models\User,email|distinct'
        ])->validate();

        DB::beginTransaction();
        try {
            $users = collect($users)->map(function(array $user): User {
                return User::create($user);
            });
            // todo implement correct sendMail method
            // $this->sendEmail($users);
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
        DB::commit();

        return $users;
    }


    /**
     * Send email to the users
     *
     * @param  array
     * @return boolean
     */
    private function sendEmail(Collection $users)
    {
        $users->unique('id' )->each(function(User $user) {
            $message = "Account has beed created. You can log in as <b>{$user->login}</b>";

            Mail::to($user['email'])
                ->cc('support@company.com');
        });

        return true;
    }


}
