<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterRegionEntitySeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['region_code' => 'ASWJWA1', 'region_name' => 'ASW JAWA 1', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWJWA2', 'region_name' => 'ASW JAWA 2', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWPUL1', 'region_name' => 'ASW PULAU 1', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM1', 'region_name' => 'ASW SUMATERA 1', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM2', 'region_name' => 'ASW SUMATERA 2', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM3', 'region_name' => 'ASW SUMATERA 3', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'INAJWA1', 'region_name' => 'INA JAWA 1', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INAJWA2', 'region_name' => 'INA JAWA 2', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INAPUL1', 'region_name' => 'INA PULAU 1', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INASUM1', 'region_name' => 'INA SUMATERA 1', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INASUM2', 'region_name' => 'INA SUMATERA 2', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
        ];

        foreach ($regions as $r) {
            DB::table('master_regions')->updateOrInsert(
                ['region_code' => $r['region_code']],
                [
                    'region_name' => $r['region_name'],
                    'principal_code' => $r['principal_code'],
                    'principal_name' => $r['principal_name'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $entities = [
            // ASWJWA1
            ['region_code' => 'ASWJWA1', 'region_name' => 'ASW JAWA 1', 'entity_code_principal' => 'ASW01', 'entity_name_principal' => 'ASW JABODETABEK', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWJWA1', 'region_name' => 'ASW JAWA 1', 'entity_code_principal' => 'ASW02', 'entity_name_principal' => 'ASW JAWA TIMUR 1', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWJWA1', 'region_name' => 'ASW JAWA 1', 'entity_code_principal' => 'ASW03', 'entity_name_principal' => 'ASW JAWA TIMUR 2', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],

            // ASWJWA2
            ['region_code' => 'ASWJWA2', 'region_name' => 'ASW JAWA 2', 'entity_code_principal' => 'ASW04', 'entity_name_principal' => 'ASW JAWA BARAT', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWJWA2', 'region_name' => 'ASW JAWA 2', 'entity_code_principal' => 'ASW05', 'entity_name_principal' => 'ASW JAWA TENGAH 1', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWJWA2', 'region_name' => 'ASW JAWA 2', 'entity_code_principal' => 'ASW06', 'entity_name_principal' => 'ASW JAWA TENGAH 2', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],

            // ASWPUL1
            ['region_code' => 'ASWPUL1', 'region_name' => 'ASW PULAU 1', 'entity_code_principal' => 'ASW07', 'entity_name_principal' => 'ASW KALIMANTAN', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWPUL1', 'region_name' => 'ASW PULAU 1', 'entity_code_principal' => 'ASW08', 'entity_name_principal' => 'ASW SULAWESI 1', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWPUL1', 'region_name' => 'ASW PULAU 1', 'entity_code_principal' => 'ASW09', 'entity_name_principal' => 'ASW SULAWESI 2', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWPUL1', 'region_name' => 'ASW PULAU 1', 'entity_code_principal' => 'ASW10', 'entity_name_principal' => 'ASW INDONESIA TIMUR', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],

            // ASWSUM1
            ['region_code' => 'ASWSUM1', 'region_name' => 'ASW SUMATERA 1', 'entity_code_principal' => 'ASW11', 'entity_name_principal' => 'ASW NAD 1', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM1', 'region_name' => 'ASW SUMATERA 1', 'entity_code_principal' => 'ASW12', 'entity_name_principal' => 'ASW NAD 2', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM1', 'region_name' => 'ASW SUMATERA 1', 'entity_code_principal' => 'ASW13', 'entity_name_principal' => 'ASW SUMUT 1', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM1', 'region_name' => 'ASW SUMATERA 1', 'entity_code_principal' => 'ASW14', 'entity_name_principal' => 'ASW SUMUT 2', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM1', 'region_name' => 'ASW SUMATERA 1', 'entity_code_principal' => 'ASW15', 'entity_name_principal' => 'ASW SUMUT 3', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],

            // ASWSUM2
            ['region_code' => 'ASWSUM2', 'region_name' => 'ASW SUMATERA 2', 'entity_code_principal' => 'ASW16', 'entity_name_principal' => 'ASW JAMBI', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM2', 'region_name' => 'ASW SUMATERA 2', 'entity_code_principal' => 'ASW17', 'entity_name_principal' => 'ASW RIAU 1', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM2', 'region_name' => 'ASW SUMATERA 2', 'entity_code_principal' => 'ASW18', 'entity_name_principal' => 'ASW RIAU 2', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM2', 'region_name' => 'ASW SUMATERA 2', 'entity_code_principal' => 'ASW19', 'entity_name_principal' => 'ASW SUMBAR', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],

            // ASWSUM3
            ['region_code' => 'ASWSUM3', 'region_name' => 'ASW SUMATERA 3', 'entity_code_principal' => 'ASW20', 'entity_name_principal' => 'ASW BENGKULU', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM3', 'region_name' => 'ASW SUMATERA 3', 'entity_code_principal' => 'ASW21', 'entity_name_principal' => 'ASW KEPULAUAN RIAU', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM3', 'region_name' => 'ASW SUMATERA 3', 'entity_code_principal' => 'ASW22', 'entity_name_principal' => 'ASW LAMBABEL', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],
            ['region_code' => 'ASWSUM3', 'region_name' => 'ASW SUMATERA 3', 'entity_code_principal' => 'ASW23', 'entity_name_principal' => 'ASW SUMSEL', 'principal_code' => 'ASW', 'principal_name' => 'ASWFOODS'],

            // INAJWA1
            ['region_code' => 'INAJWA1', 'region_name' => 'INA JAWA 1', 'entity_code_principal' => 'INA01', 'entity_name_principal' => 'INA JABODETABEK', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INAJWA1', 'region_name' => 'INA JAWA 1', 'entity_code_principal' => 'INA02', 'entity_name_principal' => 'INA JAWA TIMUR 1', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INAJWA1', 'region_name' => 'INA JAWA 1', 'entity_code_principal' => 'INA03', 'entity_name_principal' => 'INA JAWA TIMUR 2', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],

            // INAJWA2
            ['region_code' => 'INAJWA2', 'region_name' => 'INA JAWA 2', 'entity_code_principal' => 'INA04', 'entity_name_principal' => 'INA JAWA BARAT', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INAJWA2', 'region_name' => 'INA JAWA 2', 'entity_code_principal' => 'INA05', 'entity_name_principal' => 'INA JAWA TENGAH 1', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INAJWA2', 'region_name' => 'INA JAWA 2', 'entity_code_principal' => 'INA06', 'entity_name_principal' => 'INA JAWA TENGAH 2', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],

            // INAPUL1
            ['region_code' => 'INAPUL1', 'region_name' => 'INA PULAU 1', 'entity_code_principal' => 'INA07', 'entity_name_principal' => 'INA KALIMANTAN', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INAPUL1', 'region_name' => 'INA PULAU 1', 'entity_code_principal' => 'INA08', 'entity_name_principal' => 'INA SULAWESI', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],

            // INASUM1
            ['region_code' => 'INASUM1', 'region_name' => 'INA SUMATERA 1', 'entity_code_principal' => 'INA09', 'entity_name_principal' => 'INA NAD', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INASUM1', 'region_name' => 'INA SUMATERA 1', 'entity_code_principal' => 'INA10', 'entity_name_principal' => 'INA RIAU', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INASUM1', 'region_name' => 'INA SUMATERA 1', 'entity_code_principal' => 'INA11', 'entity_name_principal' => 'INA SUMUT', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INASUM1', 'region_name' => 'INA SUMATERA 1', 'entity_code_principal' => 'INA011', 'entity_name_principal' => 'INA SUMBAR', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INASUM1', 'region_name' => 'INA SUMATERA 1', 'entity_code_principal' => 'INA16', 'entity_name_principal' => 'INA KEPRI', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],

            // INASUM2
            ['region_code' => 'INASUM2', 'region_name' => 'INA SUMATERA 2', 'entity_code_principal' => 'INA12', 'entity_name_principal' => 'INA BENGKULU', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INASUM2', 'region_name' => 'INA SUMATERA 2', 'entity_code_principal' => 'INA13', 'entity_name_principal' => 'INA JAMBI', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INASUM2', 'region_name' => 'INA SUMATERA 2', 'entity_code_principal' => 'INA14', 'entity_name_principal' => 'INA LAMPUNG', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
            ['region_code' => 'INASUM2', 'region_name' => 'INA SUMATERA 2', 'entity_code_principal' => 'INA015', 'entity_name_principal' => 'INA SUMSEL', 'principal_code' => 'INA', 'principal_name' => 'INAFOODS'],
        ];

        foreach ($entities as $e) {
            DB::table('master_entities')->updateOrInsert(
                ['entity_code_principal' => $e['entity_code_principal']],
                [
                    'region_code' => $e['region_code'],
                    'region_name' => $e['region_name'],
                    'entity_name_principal' => $e['entity_name_principal'],
                    'principal_code' => $e['principal_code'],
                    'principal_name' => $e['principal_name'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
