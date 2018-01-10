<?php

namespace App\Repositories;

use App\EmailAddress;
use App\User;
use App\UserSocialProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class UserRepository extends Repository {

    protected static $model = \App\User::class;

    protected static function saveOne($model, $fields)
    {
        if(!$model) {
            $model = $this->newModel();
        }

        // Fill basic information.
        $model->fill(Arr::only($fields, ['name', 'username', 'timezone', 'avatar']));

        if(Arr::has($fields, 'password')) {
            // If the password was provided, set it too.
            $model->password = Arr::get($fields, 'password');
        }

        // Commit it to the database.
        $model->save();

        // Save e-mail addresses.
        static::saveEmails($model, Arr::get($fields, 'email_addresses'));

        // Sync the roles next, since it requires having a real DB record
        $model->syncRoles(Arr::has($fields, 'roles') ? Arr::get($fields, 'roles') : []);

        return $model;
    }

    public static function saveEmails(User $user, $emails)
    {
        if($emails) {
            // Cast e-mails to array, if just a string was passed
            if(is_string($emails)) {
                $emails = [$emails];
            }

            // Cast input to lowercase
            array_walk($emails, function (&$value) {
                $value = strtolower($value);
            });

            // Use only unique addresses
            $emails = array_unique($emails);

            // Filter out non-empty array members
            $emails = array_filter($emails);

            foreach($emails as $k => $v)
            {
                if(!is_object($v) || class_basename($v) !== 'EmailAddress')
                {
                    if(!$v) {
                        unset($emails[$k]);
                        continue;
                    }

                    $v = strtolower($v);

                    if(!is_array($v)) {
                        $v = ['address' => strtolower($v)];
                    }

                    $emails[$k] = new EmailAddress($v);
                }
            }
        }

        $toInsert = collect($emails);
        $toDelete = $user->emails;

        // Check toInsert against toDelete, ignoring any that are equal.
        if($toInsert->count() && $toDelete->count())
        {
            foreach($toInsert as $kIns => $ins)
            {
                foreach($toDelete as $kDel => $del)
                {
                    if($ins->address == $del->address)
                    {
                        // Addresses are the same, ignore.
                        $toInsert->pull($kIns);
                        $toDelete->pull($kDel);
                        break;
                    }
                }
            }
        }

        foreach($toInsert as $kins => $ins)
        {
            // First check if any of these were previously deleted, and un-delete them.
            $deleted = EmailAddress
                ::where('address', array_get($ins, 'address'))
                ->withTrashed()
                ->first();

            if($deleted)
            {
                $deleted->emailable()->associate($user);
                $deleted->restore();
                unset($toInsert[$kins]);
            }
        }

        // If any are left in toInsert, insert them now.
        foreach($toInsert as $ins)
        {
            $user->emails()->save($ins);
        }

        // If any are left in toDelete, delete them now.
        if($toDelete) {
            foreach($toDelete as $deleteEmail) {
                $deleteEmail->delete();
            }
        }

        return true;
    }

    public static function savePhones(User $user, $phones)
    {
        foreach($phones as $k => $v)
        {
            if(!is_object($v) || class_basename($v) !== 'PhoneNumber')
            {
                $phones[$k] = new PhoneNumber($v);
            }
        }

        $toInsert = $phones;
        $toDelete = $user->phones;

        // Check toInsert against toDelete, ignoring any that are equal.
        if($toInsert->count() && $toDelete->count())
        {
            foreach($toInsert as $kIns => $ins)
            {
                foreach($toDelete as $kDel => $del)
                {
                    if($ins->full_number == $del->full_number)
                    {
                        // Numbers are the same, ignore.
                        $toInsert->pull($kIns);
                        $toDelete->pull($kDel);
                        break;
                    }
                }
            }
        }

        // If any are left in toInsert, insert them now.
        foreach($toInsert as $ins)
        {
            $user->phones()->save($ins);
        }

        // If any are left in toDelete, delete them now.
        $success = PhoneNumber::destroy($toDelete->lists('id')->toArray());
    }

    public static function saveLocations(User $user, $addresses)
    {
        foreach($addresses as $k => $v)
        {
            if(!is_object($v) || class_basename($v) !== 'Address')
            {
                $addresses[$k] = new Address($v);
            }
        }

        $toInsert = $addresses;
        $toDelete = $user->addresses;

        // Check toInsert against toDelete, ignoring any that are equal.
        if($toInsert->count() && $toDelete->count())
        {
            foreach($toInsert as $kIns => $ins)
            {
                foreach($toDelete as $kDel => $del)
                {
                    if($ins->render() == $del->render())
                    {
                        // Addresses are the same, ignore.
                        $toInsert->pull($kIns);
                        $toDelete->pull($kDel);
                        break;
                    }
                }
            }
        }

        // If any are left in toInsert, insert them now.
        foreach($toInsert as $ins)
        {
            $user->addresses()->save($ins);
        }

        // If any are left in toDelete, delete them now.
        $success = Address::destroy($toDelete->lists('id')->toArray());
    }

    /**
     * Based on a provider (OAuth social login), find a user model.
     * If they don't yet exist, add them to the database.
     *
     * @param $provider
     * @param $user
     * @return User
     */
    public static function getOrCreateFromProvider($provider, $user)
    {
        $socialProfile = [
            'provider' => $provider,
            'provider_id' => $user->getId(),
        ];

        // Query for known users, by social network
        $knownUserBySocial = UserSocialProfile::where('provider_id', '=', $user->getId())
            ->where('provider', '=', $provider)
            ->first();
        $bNewSocialProfile = TRUE;

        if(!$knownUserBySocial)
        {
            // Not known by social ID and network, so check by e-mail address
            $knownUser = User::where('email', '=', $user->getEmail())->first();
        }
        else
        {
            // We know of them already so set their user model to be returned
            $knownUser = $knownUserBySocial->user;
            $bNewSocialProfile = FALSE;
        }

        if(!$knownUser)
        {
            // We don't know about this user at all, so generate anew
            $savingUser = new User([
                'email' => $user->getEmail(),
            ]);
        }
        else
        {
            // We know of this user either by social network or by e-mail
            $savingUser = $knownUser;
        }

        // Usernames the provider will provide and should be adhered to first, like Twitter username
        $providerChoices = [];

        // Fill details based on the provider
        switch($provider)
        {
            case 'facebook':
                // The "friendly" name is either their nickname, if provided, else their first name.
                $savingUser->name = $user->getNickname() ? $user->getNickname() : $user->user['first_name'];

                if(is_numeric($user->user['timezone']))
                {
                    // Guess the timezone based on offset
//                    $savingUser['timezone'] = timezone_name_from_abbr("", $user->user['timezone']*3600, false);
                }

                $socialProfile['link'] = $user->user['link'];
                break;
            case 'twitter':
                $providerChoices[] = $user->getName();
                $providerChoices[] = $user->getNickname();

                // The "friendly" name is either their nickname, if provided, else their first name.
                $savingUser->name = $user->getName();

//                $socialProfile['link'] = $user->user['url'];
                break;
            case 'google':
                if(isset($user['url']) && $user['url'])
                {
                    $possibleUrlName = substr_replace($user['url'], '', 0, strlen('https://plus.google.com/+'));
                    if(preg_match('/[a-z]+/', strtolower($possibleUrlName)))
                    {
                        $providerChoices[] = $possibleUrlName;
                    }
                }

                if(isset($user['name']['givenName']))
                {
                    $savingUser->name = $user['name']['givenName'];
                }
                break;
        }

        $email_nickname = explode('@', (string) $savingUser->email);
        $email_nickname = array_shift($email_nickname);

        if(!$savingUser->exists)
        {
            $savingUser->username = UserRepository::getUniqueUsername(array_merge($providerChoices, [
                $savingUser->username, // The current username is first choice (false or empty will be safely ignored)
                $email_nickname, // Also try the beginning of their e-mail
            ]));
        }

        if($savingUser->save() && $bNewSocialProfile)
        {
            // The user was saved, and a new profile is needed
            $socialProfile = new UserSocialProfile($socialProfile);
            $savingUser->socialProfiles()->save($socialProfile);
        }

        return $savingUser;
    }

    /**
     * Get a totally unique username for this user.
     *
     * @param $choices
     * @return string
     */
    private static function getUniqueUsername($choices)
    {
        $choices = array_filter($choices);
        $primary_choice = $choices[key($choices)];
        $valid = FALSE;

        while(count($choices))
        {
            $check = array_shift($choices);
            if(!User::where('username', '=', $check)->first())
            {
                $valid = $check;
                break;
            }
        }

        if($valid)
        {
            // One of the user's choices was valid
            return $valid;
        }

        // None of the user's choices was valid.
        $lame_choice = $primary_choice .= '-' . rand(1337, 4815162342);
        while(User::where('username', '=', $lame_choice)->first())
        {
            $lame_choice = $primary_choice .= '-' . rand(1337, 4815162342);
        }

        // Lame.
        return $lame_choice;
    }

    public function administrators()
    {
        return $this->newModel()->whereHas('roles', function ($query) {
            $query->where('slug', 'developer');
        })->get();
    }
}
