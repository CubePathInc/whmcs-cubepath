<?php
/**
 * CubePath Cloud WHMCS Module - Activity Log
 *
 * Per-service log of the actions run through the module (panel, admin buttons
 * and automation). The CubePath activity API is not available to API tokens,
 * so the module keeps its own record.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

if (!class_exists('ActivityHelper'))
{
    class ActivityHelper
    {
        const TABLE = 'mod_cubepath_activity';

        /** Rows kept per service; older ones are pruned when new ones are written. */
        const KEEP = 200;

        public static function ensureTable()
        {
            static $checked = false;
            if ($checked)
            {
                return;
            }
            $checked = true;

            $schema = Capsule::schema();
            if ($schema->hasTable(self::TABLE))
            {
                return;
            }

            $schema->create(self::TABLE, function ($table) {
                $table->increments('id');
                $table->integer('service_id')->index();
                $table->string('actor', 16);
                $table->integer('admin_id')->nullable();
                $table->string('action', 64);
                $table->boolean('success')->default(true);
                $table->text('details')->nullable();
                $table->string('ip', 45)->nullable();
                $table->dateTime('created_at');
            });
        }

        /**
         * Record an action. Logging never breaks the action being logged.
         *
         * @param int         $serviceId
         * @param string      $actor   client, admin or system
         * @param string      $action  e.g. power_start, reinstall
         * @param bool        $success
         * @param string|null $details
         */
        public static function log($serviceId, $actor, $action, $success = true, $details = null)
        {
            try
            {
                self::ensureTable();

                $adminId = isset($_SESSION['adminid']) ? (int)$_SESSION['adminid'] : null;
                Capsule::table(self::TABLE)->insert(array(
                    'service_id' => (int)$serviceId,
                    'actor'      => $actor,
                    'admin_id'   => $actor === 'admin' ? $adminId : null,
                    'action'     => $action,
                    'success'    => $success ? 1 : 0,
                    'details'    => $details !== null ? mb_substr((string)$details, 0, 1000) : null,
                    'ip'         => isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : null,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ));

                $cutoff = Capsule::table(self::TABLE)
                    ->where('service_id', (int)$serviceId)
                    ->orderBy('id', 'desc')
                    ->skip(self::KEEP)
                    ->take(1)
                    ->value('id');
                if ($cutoff)
                {
                    Capsule::table(self::TABLE)->where('service_id', (int)$serviceId)->where('id', '<=', $cutoff)->delete();
                }
            }
            catch (\Exception $e)
            {
                logActivity('CubePath: could not write the activity log: ' . $e->getMessage());
            }
        }

        /**
         * Latest entries for a service, newest first.
         *
         * @param int $serviceId
         * @param int $limit
         * @return array
         */
        public static function recent($serviceId, $limit = 100)
        {
            self::ensureTable();

            $rows = Capsule::table(self::TABLE)
                ->where('service_id', (int)$serviceId)
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get();

            $entries = array();
            foreach ($rows as $row)
            {
                $entries[] = array(
                    'id'         => (int)$row->id,
                    'created_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime($row->created_at . ' UTC')),
                    'actor'      => $row->actor,
                    'action'     => $row->action,
                    'details'    => $row->details,
                    'success'    => (bool)$row->success,
                );
            }

            return $entries;
        }
    }
}
