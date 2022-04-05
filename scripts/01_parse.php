<?php
$rawPath = dirname(__DIR__) . '/raw';
if (!file_exists($rawPath)) {
    mkdir($rawPath, 0777);
    $json = json_decode(file_get_contents('https://edutreemap.moe.edu.tw/trees_API/api/Map/GetMapIndex'), true);
    file_put_contents($rawPath . '/schools.json', json_encode($json['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
$jsonPath = dirname(__DIR__) . '/docs/json';
if (!file_exists($jsonPath)) {
    mkdir($jsonPath, 0777, true);
}
$pool = [];

foreach (glob($rawPath . '/code/*.csv') as $csvFile) {
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    $codeKey = false;
    foreach ($head as $k => $v) {
        if (false !== strpos($v, '代碼')) {
            $codeKey = $k;
        }
    }
    if (false === $codeKey) {
        fgetcsv($fh, 2048);
        $head = fgetcsv($fh, 2048);
        foreach ($head as $k => $v) {
            if (false !== strpos($v, '代碼')) {
                $codeKey = $k;
            }
        }
    }
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        if (!isset($pool[$line[$codeKey]])) {
            $pool[$line[$codeKey]] = $data;
        } else {
            $pool[$line[$codeKey]] = array_merge($pool[$line[$codeKey]], $data);
        }
    }
}

/*
    [0] => CityList
    [1] => TownList
    [2] => SchoolList
    [3] => TreeTypeList
*/
$schools = json_decode(file_get_contents($rawPath . '/schools.json'), true);
$treeTypes = [];
$jsonTree = [];
foreach ($schools['TreeTypeList'] as $item) {
    $treeTypes[$item['Value']] = $item['Text'];
    $jsonTree[$item['Value']] = [
        'type' => 'FeatureCollection',
        'features' => [],
    ];
}
file_put_contents($jsonPath . '/tree.json', json_encode($treeTypes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$customMap = [
    '1240201' => '124F01',
    '3830201' => '383F01',
    '5930201' => '593F01',
    '0330201' => '033F01',
    '5830201' => '583F01',
    '0130201' => '013F01',
    '1930202' => '193F02',
    '2100201' => '210F01',
    '4130202' => '413F02',
    '0630201' => '063F01',
    '4130201' => '413F01',
    '3630201' => '363F01',
    '5430201' => '543F01',
    '1930201' => '193F01',
    '0200201' => '020F01',
    '1500201' => '150F01',
    '0800201' => '080F01',
    '1300201' => '130F01',
    '0500201' => '050F01',
    '1700201' => '170F01',
    '0900201' => '090F01',
    '0400201' => '040F01',
    '2000201' => '200F01',
    '0700202' => '070F02',
    '1400201' => '140F01',
    '1100201' => '110F01',
    '0700201' => '070F01',
];

$jsonSchool = [
    'type' => 'FeatureCollection',
    'features' => [],
];

foreach ($schools['SchoolList'] as $school) {
    if (empty($school['Parent']) || empty($school['Parent2'])) {
        continue;
    }
    if (isset($customMap[$school['Value']])) {
        $school['Value'] = $customMap[$school['Value']];
    }
    $metaPath = $rawPath . '/meta/' . $school['Parent'] . '/' . $school['Parent2'];
    if (!file_exists($metaPath)) {
        mkdir($metaPath, 0777, true);
    }
    $metaFile = $metaPath . '/' . $school['Value'] . '.json';
    if (!file_exists($metaFile)) {
        $json = json_decode(file_get_contents("https://edutreemap.moe.edu.tw/trees_API/api/Map/GetMapSearch?city={$school['Parent']}&town={$school['Parent2']}&treeType=&school={$school['Value']}"), true);
        if (!empty($json)) {
            file_put_contents($metaFile, json_encode($json['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    if (!file_exists($metaFile)) {
        continue;
    }
    $meta = json_decode(file_get_contents($metaFile), true);


    $treePath = $rawPath . '/tree/' . $school['Parent'] . '/' . $school['Parent2'];
    if (!file_exists($treePath)) {
        mkdir($treePath, 0777, true);
    }
    $treeFile = $treePath . '/' . $school['Value'] . '.json';
    if (!file_exists($treeFile) || filesize($treeFile) === 0) {
        if (!empty($meta['treeBound']['xmax'])) {
            file_put_contents($treeFile, file_get_contents("https://edutreemap.moe.edu.tw/trees_API/api/Map/GetPointGroup?n={$meta['treeBound']['ymax']}&w={$meta['treeBound']['xmin']}&s={$meta['treeBound']['ymin']}&e={$meta['treeBound']['xmax']}"));
        }
    }
    if (file_exists($treeFile)) {
        $f = [
            'type' => 'Feature',
            'properties' => [
                'code' => $school['Value'],
                'count' => $meta['treeCount'],
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    floatval(($meta['treeBound']['xmax'] + $meta['treeBound']['xmin']) / 2),
                    floatval(($meta['treeBound']['ymax'] + $meta['treeBound']['ymin']) / 2),
                ],
            ],
        ];
        $jsonSchool['features'][] = $f;

        foreach ($meta['treeDistinctList'] as $item) {
            $f['properties']['count'] = $item['count'];
            $jsonTree[$item['name']]['features'][] = $f;
        }
    }
    $tree = json_decode(file_get_contents($treeFile), true);
}

file_put_contents($jsonPath . '/school.json', json_encode($jsonSchool, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$jsonTreePath = $jsonPath . '/tree';
if (!file_exists($jsonTreePath)) {
    mkdir($jsonTreePath, 0777);
}
foreach ($jsonTree as $code => $fc) {
    file_put_contents($jsonTreePath . '/' . $code . '.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
