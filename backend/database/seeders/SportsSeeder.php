<?php
namespace Database\Seeders;
class SportsSeeder {
    public function run() {
        $sports = [
            ['name' => 'Badminton', 'code' => 'BDM', 'slug' => 'badminton', 'is_team_sport' => 0, 'min_team_size' => 1, 'max_team_size' => 2],
            ['name' => 'Cricket', 'code' => 'CRI', 'slug' => 'cricket', 'is_team_sport' => 1, 'min_team_size' => 11, 'max_team_size' => 15],
            ['name' => 'Football', 'code' => 'FTB', 'slug' => 'football', 'is_team_sport' => 1, 'min_team_size' => 11, 'max_team_size' => 25],
        ];
        foreach ($sports as $sport) {
            \Illuminate\Database\Capsule\Manager::table('sports')->updateOrInsert(
                ['name' => $sport['name'], 'organization_id' => 1],
                [
                    'code' => $sport['code'],
                    'slug' => $sport['slug'],
                    'is_team_sport' => $sport['is_team_sport'],
                    'min_team_size' => $sport['min_team_size'],
                    'max_team_size' => $sport['max_team_size'],
                    'is_global' => 1,
                    'status' => 'active'
                ]
            );
        }
    }
}
