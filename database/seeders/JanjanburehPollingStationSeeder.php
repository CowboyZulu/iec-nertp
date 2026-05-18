<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds all JANJANBUREH (Central River Region) polling stations.
 * Source: Wards (1).pdf — Pages 27-32
 *
 * Constituencies:
 *   NIAMINA DANKUNKU   601xxx | NIAMINA WEST       602xxx | NIAMINA EAST        603xxx
 *   LOWER FULLADU WEST 604xxx | JANJANBUREH        605xxx | UPPER FULLADU WEST  606xxx
 *   LOWER SALOUM       607xxx | UPPER SALOUM       608xxx | NIANIJA             609xxx
 *   NIANI              610xxx | SAMI               611xxx
 *
 * Verified coordinates (from geographic resource):
 *   Janjanbureh (McCarthy Island) 13.5358,‑14.7644
 *   Sambang Fula Kunda 13.5181,‑15.1911 · Catamina 13.4864,‑15.1936
 *   Choya 13.4561,‑15.1633 · Pappa 13.5283,‑15.2289
 *   Madina Ma Ancha 13.4472,‑15.2114 · Kudang 13.5519,‑15.0211
 *   Pateh Sam 13.5042,‑15.0878 · Mamud Fana 13.4739,‑15.0456
 *   Batti Njol 13.5208,‑15.0044 · Mbayen 13.4347,‑15.0119
 *   Maka Mbayen 13.4522,‑14.9817 · Dankunku 13.5558,‑15.2206
 *   Brikama Ba 13.5292,‑14.8944 · Bansang 13.4333,‑14.6500
 *   Galleh Manda 13.4619,‑14.7086 · Sare Sofi 13.4072,‑14.7331
 *   Ballanghar 13.6150,‑15.4880
 * All other stations marked // estimated.
 */
class JanjanburehPollingStationSeeder extends Seeder
{
    private const OFFSET = 0.000018;

    private int $electionId;
    private int $created = 0;

    public function run(): void
    {
        $this->electionId = Election::where('slug', 'gambia-2021-presidential')->value('id')
            ?? throw new \RuntimeException('[JanjanburehSeeder] Election gambia-2021-presidential not found.');

        $this->command->info('▶  Seeding Janjanbureh (Central River Region) polling stations...');

        $region = $this->node('admin_area', 'JANJANBUREH', 'JJB', null, 'admin-area-approver');

        foreach ($this->schema() as $c) {
            $cn = $this->node('constituency', $c['name'], $c['code'], $region->id, 'constituency-approver');
            foreach ($c['wards'] as $w) {
                $wn = $this->node('ward', $w['name'], $w['code'], $cn->id, 'ward-approver');
                foreach ($w['stations'] as $s) {
                    $this->plant($wn->id, $s);
                }
            }
        }

        $this->command->info("✅  Janjanbureh done — {$this->created} records created/verified.");
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function plant(int $wardId, array $s): void
    {
        foreach ($s['ps_codes'] as $i => $code) {
            [$lat, $lng] = $this->nudge($s['lat'], $s['lng'], $i);
            $officer     = $this->officer($code);
            PollingStation::firstOrCreate(['code' => $code], [
                'election_id'         => $this->electionId,
                'ward_id'             => $wardId,
                'name'                => $s['name'],
                'latitude'            => round($lat, 7),
                'longitude'           => round($lng, 7),
                'registered_voters'   => $s['voters'] ?? rand(120, 400),
                'assigned_officer_id' => $officer->id,
                'is_active'           => true,
            ]);
            $this->created++;
        }
    }

    private function nudge(float $lat, float $lng, int $i): array
    {
        if ($i === 0) return [$lat, $lng];
        $mag  = self::OFFSET * (intdiv($i - 1, 8) + 1);
        $diag = $mag * 0.707;
        return match ($i % 8) {
            1 => [$lat + $mag,  $lng        ],
            2 => [$lat,         $lng + $mag ],
            3 => [$lat - $mag,  $lng        ],
            4 => [$lat,         $lng - $mag ],
            5 => [$lat + $diag, $lng + $diag],
            6 => [$lat - $diag, $lng + $diag],
            7 => [$lat - $diag, $lng - $diag],
            0 => [$lat + $diag, $lng - $diag],
        };
    }

    private function node(string $level, string $name, string $code, ?int $parentId, string $role): AdministrativeHierarchy
    {
        $node = AdministrativeHierarchy::firstOrCreate(
            ['election_id' => $this->electionId, 'level' => $level, 'code' => $code],
            ['parent_id' => $parentId, 'name' => $name, 'slug' => Str::slug("{$name}-jjb")]
        );
        if (!$node->assigned_approver_id) {
            $email    = Str::slug($name) . ".{$level}@janjanbureh.iec.local";
            $approver = User::firstOrCreate(['email' => $email], [
                'name'     => "{$name} Approver",
                'password' => bcrypt('password123'),
                'status'   => 'active',
            ]);
            if (!$approver->hasRole($role)) $approver->assignRole($role);
            $node->update(['assigned_approver_id' => $approver->id]);
        }
        return $node;
    }

    private function officer(string $code): User
    {
        $email   = "officer.{$code}@janjanbureh.iec.local";
        $officer = User::firstOrCreate(['email' => $email], [
            'name'     => "Officer {$code}",
            'password' => bcrypt('password123'),
            'status'   => 'active',
        ]);
        if (!$officer->hasRole('polling-officer')) $officer->assignRole('polling-officer');
        return $officer;
    }

    // ─── data schema ──────────────────────────────────────────────────────────

    private function schema(): array
    {
        return [

            /* ══════════════════════════════════════════════════════════════════
             * 601xxx  NIAMINA DANKUNKU
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'NIAMINA DANKUNKU', 'code' => 'JJB-ND',
                'wards' => [
                    [
                        'name' => 'DANKUNKU', 'code' => 'JJB-ND-DK',
                        'stations' => [
                            ['name' => 'JESSADI',                               'lat' => 13.52500, 'lng' => -15.20000, 'ps_codes' => ['601011']], // estimated
                            ['name' => 'BARO KUNDA',                            'lat' => 13.52800, 'lng' => -15.21000, 'ps_codes' => ['601021','601022']], // estimated
                            ['name' => 'WELLINGARA YORO BAH (KERR LAYIN)',      'lat' => 13.53000, 'lng' => -15.21500, 'ps_codes' => ['601031','601032']], // estimated
                            ['name' => 'WELINGARA ELO (NIANI KUNDA)',           'lat' => 13.53100, 'lng' => -15.22000, 'ps_codes' => ['601041']], // estimated
                            ['name' => 'DANKUNKU MANDINKA',                     'lat' => 13.55580, 'lng' => -15.22060, 'ps_codes' => ['601051','601052']],
                            ['name' => 'TOUBA WOLLOF (SAMBANG WOLLOF)',         'lat' => 13.53400, 'lng' => -15.22500, 'ps_codes' => ['601061']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 602xxx  NIAMINA WEST
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'NIAMINA WEST', 'code' => 'JJB-NW',
                'wards' => [
                    [
                        'name' => 'CATAMINA', 'code' => 'JJB-NW-CT',
                        'stations' => [
                            ['name' => 'SAMBANG FULA KUNDA',                'lat' => 13.51810, 'lng' => -15.19110, 'ps_codes' => ['602011','602012']],
                            ['name' => 'CATAMINA',                          'lat' => 13.48640, 'lng' => -15.19360, 'ps_codes' => ['602021','602022']],
                            ['name' => 'CHOYA',                             'lat' => 13.45610, 'lng' => -15.16330, 'ps_codes' => ['602031']],
                            ['name' => 'PAPPA',                             'lat' => 13.52830, 'lng' => -15.22890, 'ps_codes' => ['602041']],
                            ['name' => 'DALABA',                            'lat' => 13.53000, 'lng' => -15.23000, 'ps_codes' => ['602051']], // estimated
                            ['name' => 'NANA',                              'lat' => 13.48000, 'lng' => -15.20000, 'ps_codes' => ['602061']], // estimated
                            ['name' => 'MADINA MA ANCHA (KERR MALIMA)',     'lat' => 13.44720, 'lng' => -15.21140, 'ps_codes' => ['602071','602072']],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 603xxx  NIAMINA EAST
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'NIAMINA EAST', 'code' => 'JJB-NE',
                'wards' => [
                    [
                        'name' => 'JARENG', 'code' => 'JJB-NE-JR',
                        'stations' => [
                            ['name' => 'JARENG',                    'lat' => 13.52000, 'lng' => -15.10000, 'ps_codes' => ['603011','603012']], // estimated
                            ['name' => 'PAKALA KERR BIRAM',         'lat' => 13.50500, 'lng' => -15.09000, 'ps_codes' => ['603021']], // estimated
                            ['name' => 'JOCKUL',                    'lat' => 13.51000, 'lng' => -15.08000, 'ps_codes' => ['603031']], // estimated
                            ['name' => 'BANTANTO',                  'lat' => 13.51500, 'lng' => -15.07000, 'ps_codes' => ['603041']], // estimated
                            ['name' => 'BATTI NJOL',                'lat' => 13.52080, 'lng' => -15.00440, 'ps_codes' => ['603051','603052']],
                            ['name' => 'MAMUD FANA',                'lat' => 13.47390, 'lng' => -15.04560, 'ps_codes' => ['603061','603062']],
                            ['name' => 'MBAYEN',                    'lat' => 13.43470, 'lng' => -15.01190, 'ps_codes' => ['603071']],
                        ],
                    ],
                    [
                        'name' => 'KUDANG', 'code' => 'JJB-NE-KD',
                        'stations' => [
                            ['name' => 'MAKA MBAYEN',           'lat' => 13.45220, 'lng' => -14.98170, 'ps_codes' => ['603081','603082']],
                            ['name' => 'KAOLONG',               'lat' => 13.50000, 'lng' => -15.02000, 'ps_codes' => ['603091']], // estimated
                            ['name' => 'SOTOKOI',               'lat' => 13.50500, 'lng' => -15.01000, 'ps_codes' => ['603101','603102']], // estimated
                            ['name' => 'KUDANG',                'lat' => 13.55190, 'lng' => -15.02110, 'ps_codes' => ['603111','603112','603113']],
                            ['name' => 'PATEH SARM',            'lat' => 13.50420, 'lng' => -15.08780, 'ps_codes' => ['603121','603122']],
                            ['name' => 'SAMBEL KUNDA',          'lat' => 13.50000, 'lng' => -15.05000, 'ps_codes' => ['603131','603132']], // estimated
                            ['name' => 'SINCHU GUNDO',          'lat' => 13.50200, 'lng' => -15.04500, 'ps_codes' => ['603141','603142']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 604xxx  LOWER FULLADU WEST
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'LOWER FULLADU WEST', 'code' => 'JJB-LF',
                'wards' => [
                    [
                        'name' => 'BRIKAMABA', 'code' => 'JJB-LF-BK',
                        'stations' => [
                            ['name' => 'SARE MALANG',       'lat' => 13.53000, 'lng' => -14.89000, 'ps_codes' => ['604011']], // estimated
                            ['name' => 'JAHALLY',           'lat' => 13.51800, 'lng' => -14.92000, 'ps_codes' => ['604021','604022']], // estimated
                            ['name' => 'MADINA MFALLY',     'lat' => 13.52000, 'lng' => -14.91000, 'ps_codes' => ['604031','604032']], // estimated
                            ['name' => 'BRIKMANDING',       'lat' => 13.52200, 'lng' => -14.90000, 'ps_codes' => ['604041']], // estimated
                            ['name' => 'BRIKAMABA',         'lat' => 13.52920, 'lng' => -14.89440, 'ps_codes' => ['604051','604052','604053']],
                        ],
                    ],
                    [
                        'name' => 'KEREWAN', 'code' => 'JJB-LF-KW',
                        'stations' => [
                            ['name' => 'DASILAMEH',                         'lat' => 13.51000, 'lng' => -14.95000, 'ps_codes' => ['604061','604062','604063']], // estimated
                            ['name' => 'SARUJA',                            'lat' => 13.55000, 'lng' => -14.94000, 'ps_codes' => ['604071','604072']], // estimated
                            ['name' => 'BOYRAM DENTON',                     'lat' => 13.55200, 'lng' => -14.93500, 'ps_codes' => ['604081','604082']], // estimated
                            ['name' => 'MISERA JOBEN',                      'lat' => 13.55400, 'lng' => -14.93000, 'ps_codes' => ['604091','604092']], // estimated
                            ['name' => 'FASS ABDOU',                        'lat' => 13.55600, 'lng' => -14.92500, 'ps_codes' => ['604101','604102']], // estimated
                            ['name' => 'KEREWAN FULA (KEREWAN SAMBA SIRA)',  'lat' => 13.54690, 'lng' => -14.94530, 'ps_codes' => ['604111','604112']], // estimated
                            ['name' => 'KEREWAN MANDINKA',                  'lat' => 13.54800, 'lng' => -14.94400, 'ps_codes' => ['604121']], // estimated
                            ['name' => 'TAIFA CHENDOU (TAIFA SAIKOU)',       'lat' => 13.54900, 'lng' => -14.94200, 'ps_codes' => ['604131','604132']], // estimated
                        ],
                    ],
                    [
                        'name' => 'FULABANTANG', 'code' => 'JJB-LF-FB',
                        'stations' => [
                            ['name' => 'GIDDA',                     'lat' => 13.53000, 'lng' => -14.85000, 'ps_codes' => ['604141','604142']], // estimated
                            ['name' => 'SARE NGAI-TABANDING NGAI',  'lat' => 13.47390, 'lng' => -14.30190, 'ps_codes' => ['604151']], // estimated
                            ['name' => 'PATCHARR',                  'lat' => 13.52000, 'lng' => -14.82000, 'ps_codes' => ['604161']], // estimated
                            ['name' => 'FULABANTANG',               'lat' => 13.50000, 'lng' => -14.88000, 'ps_codes' => ['604171','604172','604173']], // estimated
                            ['name' => 'FARABA',                    'lat' => 13.51000, 'lng' => -14.86000, 'ps_codes' => ['604181','604182']], // estimated
                            ['name' => 'SANKULAY KUNDA',            'lat' => 13.52940, 'lng' => -14.77420, 'ps_codes' => ['604191']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 605xxx  JANJANBUREH (McCarthy Island)
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'JANJANBUREH', 'code' => 'JJB-JB',
                'wards' => [
                    [
                        'name' => 'McCARTHY', 'code' => 'JJB-JB-MC',
                        'stations' => [
                            ['name' => 'JANJANBUREH (MCCARHTY)', 'lat' => 13.53580, 'lng' => -14.76440, 'ps_codes' => ['605011','605012']],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 606xxx  UPPER FULLADU WEST
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'UPPER FULLADU WEST', 'code' => 'JJB-UF',
                'wards' => [
                    [
                        'name' => 'DARU', 'code' => 'JJB-UF-DR',
                        'stations' => [
                            ['name' => 'TANDI',                     'lat' => 13.42000, 'lng' => -14.68000, 'ps_codes' => ['606011']], // estimated
                            ['name' => 'DARU',                      'lat' => 13.44190, 'lng' => -14.91250, 'ps_codes' => ['606021','606022']], // estimated
                            ['name' => 'SANTANTO BUBU',             'lat' => 13.43500, 'lng' => -14.70000, 'ps_codes' => ['606031','606032']], // estimated
                            ['name' => 'CHA KUNDA MADINA',          'lat' => 13.43000, 'lng' => -14.71000, 'ps_codes' => ['606041']], // estimated
                            ['name' => 'FASS BELAL',                'lat' => 13.42500, 'lng' => -14.72000, 'ps_codes' => ['606131','606132']], // estimated
                            ['name' => 'SARE PATEH JAWO',           'lat' => 13.42000, 'lng' => -14.73000, 'ps_codes' => ['606141','606142']], // estimated
                        ],
                    ],
                    [
                        'name' => 'SARE SOFIE', 'code' => 'JJB-UF-SF',
                        'stations' => [
                            ['name' => 'SARE SOFIE',            'lat' => 13.40720, 'lng' => -14.73310, 'ps_codes' => ['606051','606052']],
                            ['name' => 'CHARGEL',               'lat' => 13.40000, 'lng' => -14.74000, 'ps_codes' => ['606061']], // estimated
                            ['name' => 'SARE SILLERI',          'lat' => 13.39000, 'lng' => -14.75000, 'ps_codes' => ['606071','606072']], // estimated
                            ['name' => 'LIBRASS',               'lat' => 13.38000, 'lng' => -14.76000, 'ps_codes' => ['606081']], // estimated
                            ['name' => 'NDIKIRI KUNDA',         'lat' => 13.37000, 'lng' => -14.77000, 'ps_codes' => ['606091']], // estimated
                            ['name' => 'LALA GUI (SARE CHEWTO)','lat' => 13.36000, 'lng' => -14.78000, 'ps_codes' => ['606101']], // estimated
                            ['name' => 'DOBANG KUNDA.KEBBA',    'lat' => 13.35000, 'lng' => -14.79000, 'ps_codes' => ['606111']], // estimated
                            ['name' => 'BANTANTO',              'lat' => 13.34000, 'lng' => -14.80000, 'ps_codes' => ['606121','606122']], // estimated
                        ],
                    ],
                    [
                        'name' => 'GALLEH', 'code' => 'JJB-UF-GL',
                        'stations' => [
                            ['name' => 'SAM PATEH (JAHANKA)',               'lat' => 13.46190, 'lng' => -14.70860, 'ps_codes' => ['606151','606152']],
                            ['name' => 'PATEH GAI (KERR PATEH GAI)',        'lat' => 13.46300, 'lng' => -14.71000, 'ps_codes' => ['606161']], // estimated
                            ['name' => 'NGAYEN (KERR NJAGA)',               'lat' => 13.46400, 'lng' => -14.71200, 'ps_codes' => ['606171']], // estimated
                            ['name' => 'TUBA OUSMAN (KERR OUSMAN BOYE)',    'lat' => 13.46500, 'lng' => -14.71400, 'ps_codes' => ['606181']], // estimated
                            ['name' => 'MEDINA TUNJANG',                    'lat' => 13.46600, 'lng' => -14.71600, 'ps_codes' => ['606191']], // estimated
                            ['name' => 'GALLEH MANDA',                      'lat' => 13.46190, 'lng' => -14.70860, 'ps_codes' => ['606201','606202']],
                            ['name' => 'WELLINGARA DEMBA KANDEH',           'lat' => 13.46800, 'lng' => -14.72000, 'ps_codes' => ['606211']], // estimated
                        ],
                    ],
                    [
                        'name' => 'BANSANG', 'code' => 'JJB-UF-BN',
                        'stations' => [
                            ['name' => 'YORO BERI KUNDA MANDINKA',  'lat' => 13.43330, 'lng' => -14.65000, 'ps_codes' => ['606221']], // estimated
                            ['name' => 'BORABA',                    'lat' => 13.43200, 'lng' => -14.65200, 'ps_codes' => ['606231']], // estimated
                            ['name' => 'FUGGA',                     'lat' => 13.43100, 'lng' => -14.65400, 'ps_codes' => ['606241']], // estimated
                            ['name' => 'SOLOLO MANDINKA',           'lat' => 13.43000, 'lng' => -14.65600, 'ps_codes' => ['606251']], // estimated
                            ['name' => 'BANSANG A',                 'lat' => 13.43330, 'lng' => -14.65000, 'ps_codes' => ['606261','606262','606263','606264']],
                            ['name' => 'BANSANG B',                 'lat' => 13.43400, 'lng' => -14.64900, 'ps_codes' => ['606271','606272','606273']],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 607xxx  LOWER SALOUM
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'LOWER SALOUM', 'code' => 'JJB-LS',
                'wards' => [
                    [
                        'name' => 'BALLANGHAR', 'code' => 'JJB-LS-BL',
                        'stations' => [
                            ['name' => 'BALLANGHAR JALATO',             'lat' => 13.61000, 'lng' => -15.49000, 'ps_codes' => ['607011']], // estimated
                            ['name' => 'BALLANGHAR.KERR NDERRI',        'lat' => 13.61500, 'lng' => -15.48800, 'ps_codes' => ['607021','607022']],
                            ['name' => 'BALLANGHAR KERR LAYIN.',        'lat' => 13.61600, 'lng' => -15.48600, 'ps_codes' => ['607031','607032']],
                        ],
                    ],
                    [
                        'name' => 'KAUR', 'code' => 'JJB-LS-KR',
                        'stations' => [
                            ['name' => 'JAHOUR MANDINKA',       'lat' => 13.65000, 'lng' => -14.79000, 'ps_codes' => ['607041']], // estimated – Kaur, north bank
                            ['name' => 'GENGI WOLLOF',          'lat' => 13.65200, 'lng' => -14.79200, 'ps_codes' => ['607051','607052']], // estimated
                            ['name' => 'JIMBALA FELUNGO',       'lat' => 13.65400, 'lng' => -14.79400, 'ps_codes' => ['607061','607062']], // estimated
                            ['name' => 'SIMBARA KHAI',          'lat' => 13.65600, 'lng' => -14.79600, 'ps_codes' => ['607071']], // estimated
                            ['name' => 'KAUR JANNEH KUNDA',     'lat' => 13.65800, 'lng' => -14.79800, 'ps_codes' => ['607081','607082']], // estimated
                            ['name' => 'KAUR WHARF TOWN',       'lat' => 13.66000, 'lng' => -14.80000, 'ps_codes' => ['607091','607092','607093']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 608xxx  UPPER SALOUM
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'UPPER SALOUM', 'code' => 'JJB-US',
                'wards' => [
                    [
                        'name' => 'NJAU', 'code' => 'JJB-US-NJ',
                        'stations' => [
                            ['name' => 'JARENG MADI JAMA (JARENG MODOU WARR)',  'lat' => 13.50000, 'lng' => -15.30000, 'ps_codes' => ['608011','608012']], // estimated
                            ['name' => 'KERR AULDI',                            'lat' => 13.50200, 'lng' => -15.30200, 'ps_codes' => ['608021','608022']], // estimated
                            ['name' => 'BANTANTO EBRIMA KAH (KERR SULAY)',      'lat' => 13.50400, 'lng' => -15.30400, 'ps_codes' => ['608031']], // estimated
                            ['name' => 'BANTANTO KERR LAYE',                    'lat' => 13.50600, 'lng' => -15.30600, 'ps_codes' => ['608041']], // estimated
                            ['name' => 'BATTI NDARR',                           'lat' => 13.50800, 'lng' => -15.30800, 'ps_codes' => ['608051','608052']], // estimated
                            ['name' => 'NJAU',                                  'lat' => 13.51000, 'lng' => -15.31000, 'ps_codes' => ['608061']], // estimated
                        ],
                    ],
                    [
                        'name' => 'PANCHANG', 'code' => 'JJB-US-PC',
                        'stations' => [
                            ['name' => 'PANCHANG',          'lat' => 13.52000, 'lng' => -15.28000, 'ps_codes' => ['608071','608072']], // estimated
                            ['name' => 'FASS',              'lat' => 13.52200, 'lng' => -15.28200, 'ps_codes' => ['608081','608082']], // estimated
                            ['name' => 'NOIRO TUKULOR',     'lat' => 13.52400, 'lng' => -15.28400, 'ps_codes' => ['608091','608092']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 609xxx  NIANIJA
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'NIANIJA', 'code' => 'JJB-NJ',
                'wards' => [
                    [
                        'name' => 'CHAMEN', 'code' => 'JJB-NJ-CH',
                        'stations' => [
                            ['name' => 'CHAMEN',        'lat' => 13.55000, 'lng' => -14.60000, 'ps_codes' => ['609011','609012','609013']], // estimated
                            ['name' => 'PALAEILI',      'lat' => 13.55200, 'lng' => -14.60200, 'ps_codes' => ['609021']], // estimated
                            ['name' => 'KERR JEBEL',    'lat' => 13.55400, 'lng' => -14.60400, 'ps_codes' => ['609031']], // estimated
                            ['name' => 'BUDUCK',        'lat' => 13.55600, 'lng' => -14.60600, 'ps_codes' => ['609041','609042']], // estimated
                            ['name' => 'BAKADAGI',      'lat' => 13.55800, 'lng' => -14.60800, 'ps_codes' => ['609051']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 610xxx  NIANI
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'NIANI', 'code' => 'JJB-NI',
                'wards' => [
                    [
                        'name' => 'NYANGA', 'code' => 'JJB-NI-NY',
                        'stations' => [
                            ['name' => 'SAFALU',                                'lat' => 13.67000, 'lng' => -14.85000, 'ps_codes' => ['610011']], // estimated – Niani north bank
                            ['name' => 'NYANGA BANTANG',                        'lat' => 13.67200, 'lng' => -14.85200, 'ps_codes' => ['610021','610022','610023']], // estimated
                            ['name' => 'KASS WOLLOF',                           'lat' => 13.67400, 'lng' => -14.85400, 'ps_codes' => ['610031','610032']], // estimated
                            ['name' => 'DINGIRAI',                              'lat' => 13.67600, 'lng' => -14.85600, 'ps_codes' => ['610041','610042']], // estimated
                            ['name' => 'JOCKUL NDOWEN (NODWEN ANGALLEH)',       'lat' => 13.67800, 'lng' => -14.85800, 'ps_codes' => ['610051','610052']], // estimated
                            ['name' => 'GINGORY MUSTAPHA',                      'lat' => 13.68000, 'lng' => -14.86000, 'ps_codes' => ['610071','610072']], // estimated
                            ['name' => 'MBAYEN WOLLOF',                         'lat' => 13.68200, 'lng' => -14.86200, 'ps_codes' => ['610081']], // estimated
                        ],
                    ],
                    [
                        'name' => 'KUNTAUR', 'code' => 'JJB-NI-KT',
                        'stations' => [
                            ['name' => 'WASSU',                     'lat' => 13.66700, 'lng' => -14.86670, 'ps_codes' => ['610061','610062','610063']], // estimated – Kuntaur area
                            ['name' => 'KATABA ALH. OMAR',          'lat' => 13.66900, 'lng' => -14.86800, 'ps_codes' => ['610091','610092']], // estimated
                            ['name' => 'KUNTAUR WHARF TOWN',        'lat' => 13.67000, 'lng' => -14.86500, 'ps_codes' => ['610101']], // estimated
                            ['name' => 'SUKUTA',                    'lat' => 13.67100, 'lng' => -14.86400, 'ps_codes' => ['610111','610112']], // estimated
                            ['name' => 'JAKABA',                    'lat' => 13.67200, 'lng' => -14.86300, 'ps_codes' => ['610121','610122']], // estimated
                            ['name' => 'KAYAI',                     'lat' => 13.67300, 'lng' => -14.86200, 'ps_codes' => ['610131']], // estimated
                            ['name' => 'SAIT MARAM',                'lat' => 13.67400, 'lng' => -14.86100, 'ps_codes' => ['610141','610142']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 611xxx  SAMI
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'SAMI', 'code' => 'JJB-SM',
                'wards' => [
                    [
                        'name' => 'BANNI', 'code' => 'JJB-SM-BN',
                        'stations' => [
                            ['name' => 'JARUMEH KOTO',      'lat' => 13.51000, 'lng' => -14.78000, 'ps_codes' => ['611011','611012']], // estimated
                            ['name' => 'JAMALI GANYADO',    'lat' => 13.51200, 'lng' => -14.78200, 'ps_codes' => ['611021']], // estimated
                            ['name' => 'LAMIN KOTO',        'lat' => 13.51400, 'lng' => -14.78400, 'ps_codes' => ['611031']], // estimated
                            ['name' => 'BANNI',             'lat' => 13.51600, 'lng' => -14.78600, 'ps_codes' => ['611041']], // estimated
                            ['name' => 'KIBIRI',            'lat' => 13.51800, 'lng' => -14.78800, 'ps_codes' => ['611051']], // estimated
                            ['name' => 'YORNA MUSA',        'lat' => 13.52000, 'lng' => -14.79000, 'ps_codes' => ['611061']], // estimated
                            ['name' => 'KUNTING',           'lat' => 13.52200, 'lng' => -14.79200, 'ps_codes' => ['611071','611072']], // estimated
                            ['name' => 'DOBO',              'lat' => 13.52400, 'lng' => -14.79400, 'ps_codes' => ['611081','611082']], // estimated
                        ],
                    ],
                    [
                        'name' => 'KARANTABA', 'code' => 'JJB-SM-KR',
                        'stations' => [
                            ['name' => 'CHANGAI WOLLOF',    'lat' => 13.48000, 'lng' => -14.82000, 'ps_codes' => ['611091','611092']], // estimated
                            ['name' => 'TABANANI',          'lat' => 13.48200, 'lng' => -14.82200, 'ps_codes' => ['611101']], // estimated
                            ['name' => 'RANEROU SAMBA NGAI','lat' => 13.48400, 'lng' => -14.82400, 'ps_codes' => ['611111']], // estimated
                            ['name' => 'KARANTABA',         'lat' => 13.51000, 'lng' => -14.80000, 'ps_codes' => ['611151','611152']], // estimated
                        ],
                    ],
                    [
                        'name' => 'PACHONKI', 'code' => 'JJB-SM-PC',
                        'stations' => [
                            ['name' => 'SAMI NJALAL SAMBA (TORO)',  'lat' => 13.49000, 'lng' => -14.79000, 'ps_codes' => ['611121']], // estimated
                            ['name' => 'SAMI PACHONKI',             'lat' => 13.49200, 'lng' => -14.79200, 'ps_codes' => ['611131','611132']], // estimated
                            ['name' => 'SAMI MEDINA',               'lat' => 13.49400, 'lng' => -14.79400, 'ps_codes' => ['611141','611142']], // estimated
                            ['name' => 'TANDI MANDINKA',            'lat' => 13.49600, 'lng' => -14.79600, 'ps_codes' => ['611161']], // estimated
                            ['name' => 'BAYA BA (BAYA EDI BAH)',    'lat' => 13.49800, 'lng' => -14.79800, 'ps_codes' => ['611171','611172']], // estimated
                        ],
                    ],
                ],
            ],
        ];
    }
}
