<?php

use App\Permission;
use App\Role;
use App\Screen;
use Illuminate\Database\Migrations\Migration;

/**
 * Registers the two new WIP dashboards as permissioned screens, following
 * the same screen/role/grant mechanism every other page in the app already
 * uses (see App\Http\Repositories\PermissionRepository::isAuthorized()).
 * No grant is set for any role here — access starts closed and an admin
 * opts roles in via the existing Permissions admin screen, same as any
 * other screen's default state.
 */
class SeedWipDashboardScreens extends Migration
{
    private const SCREENS = [
        ['screen_code' => 'wipFloorDashboard', 'screen_name' => 'Production Floor Dashboard'],
        ['screen_code' => 'wipManagementDashboard', 'screen_name' => 'Production Management Dashboard'],
    ];

    public function up(): void
    {
        $roles = Role::all();

        foreach (self::SCREENS as $data) {
            $screen = Screen::firstOrCreate(['screen_code' => $data['screen_code']], $data);

            foreach ($roles as $role) {
                Permission::firstOrCreate([
                    'screen_id' => $screen->id,
                    'role_id' => $role->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $screens = Screen::whereIn('screen_code', array_column(self::SCREENS, 'screen_code'))->get();
        foreach ($screens as $screen) {
            Permission::where('screen_id', $screen->id)->delete();
            $screen->delete();
        }
    }
}
