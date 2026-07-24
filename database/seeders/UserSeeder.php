<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $verifiedAt = '2026-07-24 00:00:00';
        $users = [
            ['name' => '山田 太郎', 'email' => 'taro.yamada@example.com'],
            ['name' => '佐藤 花子', 'email' => 'hanako.sato@example.com'],
            ['name' => '鈴木 一郎', 'email' => 'ichiro.suzuki@example.com'],
            ['name' => '高橋 美咲', 'email' => 'misaki.takahashi@example.com'],
            ['name' => '田中 健', 'email' => 'ken.tanaka@example.com'],
        ];

        foreach ($users as $user) {
            $model = User::firstOrNew(['email' => $user['email']]);
            $model->name = $user['name'];
            $model->email_verified_at = $verifiedAt;

            if (! $model->exists) {
                $model->password = Hash::make('password');
            }

            $model->save();
        }
    }
}
