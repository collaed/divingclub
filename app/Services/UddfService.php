<?php

/**
 * UDDF (Universal Dive Data Format) import and export service.
 *
 * Handles UDDF 3.2.x XML files — the de facto standard for dive data
 * exchange between dive computers, logbook apps, and club management systems.
 *
 * Import: parses UDDF XML, extracts dive profiles, matches to events by date,
 *         populates actual depth, duration, temperature, deco stops.
 * Export: generates UDDF XML from club dive logs for use in Subsurface,
 *         MacDive, or any UDDF-compatible application.
 *
 * @author ClubCEP.eu
 *
 * @see http://www.uddf.org/
 * @see \App\Services\DanExportService — DAN DL7 export built on top of this
 */

namespace App\Services;

use App\Models\DiveGroupMember;
use App\Models\User;
use Carbon\Carbon;
use SimpleXMLElement;

class UddfService
{
    private const GENERATOR_NAME = 'DivingClub Manager';

    private const GENERATOR_VERSION = '1.0';

    private const UDDF_VERSION = '3.2.1';

    /**
     * Parse a UDDF XML file and return structured dive data.
     *
     * @return array{dives: array, divers: array, sites: array}
     */
    public function parse(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);

        $result = ['dives' => [], 'divers' => [], 'sites' => []];

        // Parse dive sites
        if (isset($xml->divesite)) {
            foreach ($xml->divesite->site ?? [] as $site) {
                $id = (string) $site['id'];
                $geo = $site->geography;
                $result['sites'][$id] = [
                    'name' => (string) ($site->name ?? $geo->location ?? ''),
                    'latitude' => isset($geo->latitude) ? (float) $geo->latitude : null,
                    'longitude' => isset($geo->longitude) ? (float) $geo->longitude : null,
                    'max_depth' => isset($site->maximumdepth) ? (float) $site->maximumdepth : null,
                ];
            }
        }

        // Parse dive profiles
        if (isset($xml->profiledata)) {
            foreach ($xml->profiledata->repetitiongroup ?? [] as $rg) {
                foreach ($rg->dive ?? [] as $dive) {
                    $d = $this->parseDive($dive);
                    if ($d) {
                        $result['dives'][] = $d;
                    }
                }
            }
        }

        return $result;
    }

    /** Parse a single <dive> element. */
    private function parseDive(SimpleXMLElement $dive): ?array
    {
        $info = $dive->informationbeforedive;
        if (! $info) {
            return null;
        }

        $datetime = isset($info->datetime) ? Carbon::parse((string) $info->datetime) : null;
        if (! $datetime) {
            return null;
        }

        // Extract max depth and duration from samples
        $maxDepth = 0;
        $duration = 0;
        $minTemp = null;
        $samples = [];
        $decoStops = [];

        if (isset($dive->samples)) {
            foreach ($dive->samples->waypoint ?? [] as $wp) {
                $time = isset($wp->divetime) ? (float) $wp->divetime : 0;
                $depth = isset($wp->depth) ? (float) $wp->depth : 0;
                $temp = isset($wp->temperature) ? (float) $wp->temperature - 273.15 : null; // Kelvin to Celsius

                $maxDepth = max($maxDepth, $depth);
                $duration = max($duration, $time);
                if ($temp !== null) {
                    $minTemp = $minTemp === null ? $temp : min($minTemp, $temp);
                }

                $samples[] = ['time' => $time, 'depth' => $depth, 'temp' => $temp];

                // Detect deco stops (ascending, depth stable for >30s)
                if (isset($wp->decostop)) {
                    $decoStops[] = [
                        'depth' => $depth,
                        'duration' => isset($wp->decostop) ? (float) $wp->decostop : 0,
                    ];
                }
            }
        }

        // Override with explicit values if present
        if (isset($info->greatestdepth)) {
            $maxDepth = (float) $info->greatestdepth;
        }
        if (isset($info->diveduration)) {
            $duration = (float) $info->diveduration;
        }

        $after = $dive->informationafterdive;

        return [
            'datetime' => $datetime,
            'max_depth' => round($maxDepth, 1),
            'duration_seconds' => (int) $duration,
            'duration_minutes' => (int) round($duration / 60),
            'min_temperature' => $minTemp !== null ? round($minTemp, 1) : null,
            'deco_stops' => $decoStops,
            'safety_stop' => ! empty($after->safetystop) || $this->detectSafetyStop($samples),
            'site_ref' => isset($info->link) ? (string) $info->link['ref'] : null,
            'notes' => isset($after->notes) ? (string) $after->notes : null,
            'tank_pressure_start' => isset($info->tankpressurebegin) ? (float) $info->tankpressurebegin / 100000 : null, // Pa to bar
            'tank_pressure_end' => isset($after->tankpressureend) ? (float) $after->tankpressureend / 100000 : null,
            'samples' => $samples,
        ];
    }

    /** Detect a safety stop: 3-6m depth held for ≥2 minutes during ascent. */
    private function detectSafetyStop(array $samples): bool
    {
        $timeAt3to6 = 0;
        $ascending = false;
        $counter = count($samples);

        for ($i = 1; $i < $counter; $i++) {
            if ($samples[$i]['depth'] < $samples[$i - 1]['depth']) {
                $ascending = true;
            }
            if ($ascending && $samples[$i]['depth'] >= 3 && $samples[$i]['depth'] <= 6) {
                $timeAt3to6 += $samples[$i]['time'] - $samples[$i - 1]['time'];
            }
        }

        return $timeAt3to6 >= 120; // 2 minutes
    }

    /**
     * Export dive data for a user as UDDF XML.
     *
     * @param  User  $user  The diver
     * @param  array  $diveGroupMembers  Collection of DiveGroupMember records with loaded relations
     */
    public function export(User $user, iterable $diveGroupMembers): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><uddf xmlns="http://www.streit.cc/uddf/3.2/" version="'.self::UDDF_VERSION.'"/>');

        // Generator
        $gen = $xml->addChild('generator');
        $gen->addChild('name', self::GENERATOR_NAME);
        $gen->addChild('version', self::GENERATOR_VERSION);
        $gen->addChild('datetime', now()->toIso8601String());

        // Diver (owner)
        $diver = $xml->addChild('diver');
        $owner = $diver->addChild('owner');
        $owner['id'] = 'diver_'.$user->id;
        $personal = $owner->addChild('personal');
        $personal->addChild('firstname', htmlspecialchars($user->detail?->first_name ?? ''));
        $personal->addChild('lastname', htmlspecialchars($user->detail?->last_name ?? ''));
        if ($user->detail?->date_of_birth) {
            $personal->addChild('birthdate', $user->detail->date_of_birth->format('Y-m-d'));
        }

        // Dive sites
        $sitesNode = $xml->addChild('divesite');
        $siteIds = [];
        foreach ($diveGroupMembers as $dgm) {
            $event = $dgm->diveGroup->event ?? null;
            $site = $event?->diveSite;
            if ($site && ! isset($siteIds[$site->id])) {
                $s = $sitesNode->addChild('site');
                $s['id'] = 'site_'.$site->id;
                $s->addChild('name', htmlspecialchars($site->name));
                $geo = $s->addChild('geography');
                if ($site->latitude) {
                    $geo->addChild('latitude', $site->latitude);
                }
                if ($site->longitude) {
                    $geo->addChild('longitude', $site->longitude);
                }
                $geo->addChild('location', htmlspecialchars($site->name));
                $siteIds[$site->id] = true;
            }
        }

        // Profile data
        $profiledata = $xml->addChild('profiledata');
        foreach ($diveGroupMembers as $dgm) {
            $group = $dgm->diveGroup;
            $event = $group->event ?? null;
            if (! $event) {
                continue;
            }

            $rg = $profiledata->addChild('repetitiongroup');
            $rg['id'] = 'rg_'.$group->id;
            $dive = $rg->addChild('dive');
            $dive['id'] = 'dive_'.$group->id.'_'.$user->id;

            $info = $dive->addChild('informationbeforedive');
            $dt = $event->event_date->format('Y-m-d').'T'.($event->start_time ?? '09:00:00');
            $info->addChild('datetime', $dt);
            if ($group->planned_depth) {
                $info->addChild('greatestdepth', $group->planned_depth);
            }
            if ($event->diveSite) {
                $link = $info->addChild('link');
                $link['ref'] = 'site_'.$event->diveSite->id;
            }

            $info->addChild('divenumber', $dgm->id);
        }

        return $xml->asXML();
    }
}
