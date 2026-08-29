<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Spatie\DbDumper\Compressors\GzipCompressor;

return [
    'backup' => [
        'name' => env('APP_NAME', 'DivingClub'),

        'source' => [
            'files' => [
                'include' => [
                    storage_path('app/public'),
                    storage_path('app/private'),
                ],
                'exclude' => [
                    storage_path('app/public/library'),
                    storage_path('app/public/event-photos'),
                    storage_path('app/public/photos'),
                    storage_path('app/public/images'),
                    storage_path('app/public/avatars'),
                    storage_path('app/private/medical'),
                    storage_path('app/private/scancards'),
                    storage_path('app/private/thumbnails'),
                    storage_path('app/backups'),
                    storage_path('app/backup-temp'),
                ],
                'follow_links' => false,
                'ignore_unreadable_directories' => true,
                'relative_path' => storage_path('app'),
            ],

            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        'database_dump_compressor' => GzipCompressor::class,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'filename_prefix' => 'backup-',
            'disks' => ['backup'],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),
        'password' => null,
        'encryption' => 'default',
        'tries' => 1,
        'retry_delay' => 0,
    ],

    'notifications' => [
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupHasFailedNotification::class => ['mail'],
            CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFY_EMAIL', 'admin@divingclub.eu'),
            'from' => ['address' => env('MAIL_FROM_ADDRESS', 'noreply@divingclub.eu'), 'name' => env('MAIL_FROM_NAME', 'DivingClub')],
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'DivingClub'),
            'disks' => ['backup'],
            'health_checks' => [
                MaximumAgeInDays::class => 7,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 30,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 0,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],

    // Offsite SFTP (used by our custom offsite upload in BackupService)
    'offsite_host' => env('BACKUP_OFFSITE_HOST'),
    'offsite_user' => env('BACKUP_OFFSITE_USER', 'dcms-backup'),
    'offsite_key' => env('BACKUP_OFFSITE_KEY', '/home/clubcep/.ssh/backup_key'),
    'offsite_dir' => env('BACKUP_OFFSITE_DIR', 'backups'),
];
