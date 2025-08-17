<?php
/**
 * Model representing info about a user
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */
declare(strict_types=1);

namespace App\Model;

use App\Repository\UserRepository;

class UserInfo
{
    public function __construct(
        public ?User $user = null,
        public ?int $userId = null,
        public string $userName = '',
        public string $realName = '',
    )
    {
        // update if user is found in database
        if ($this->user) {
            $this->realName = $this->user->get('fullName');
            $this->userName = $this->user->get('username');
        }
    }

    public static function fromUser(User $user): static
    {
        return new static(
            user: $user,
            userId: $user->get('id'),
            userName: '',
            realName: '',
        );
    }

    public static function fromJson(mixed $data, UserRepository $userRepository): ?static
    {
        if ($data === null) {
            return null;
        }

        $json = (string) $data;
        if (!json_validate($json)) {
            throw new \InvalidArgumentException('tried to load UserInfo with non-json data');
        }

        $info = json_decode($json);
        $user = $info->id ? $userRepository->findOneById($info->id) : null;
        try {
            return new static(
                user: $user,
                userId: $info->id ?? null,
                userName: $info->username ?? 'unknown',
                realName: $info->realName ?? '',
            );
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('tried to load UserInfo with invalid data');
        }
    }

    public function json(): string
    {
        $data = [];
        if ($this->userId) {
            $data['id'] = $this->userId;
        }
        if ($this->userName) {
            $data['userName'] = $this->userName;
        }
        if ($this->realName) {
            $data['realName'] = $this->realName;
        }
        return json_encode($data);
    }
}
