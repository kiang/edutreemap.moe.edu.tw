<?php
$rawPath = dirname(__DIR__) . '/raw';
if (!file_exists($rawPath)) {
    mkdir($rawPath, 0777);
    $json = json_decode(file_get_contents('https://edutreemap.moe.edu.tw/trees_API/api/Map/GetMapIndex'), true);
    file_put_contents($rawPath . '/schools.json', json_encode($json['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
$schools = json_decode(file_get_contents($rawPath . '/schools.json'), true);

foreach ($schools['SchoolList'] as $school) {
    if (empty($school['Parent']) || empty($school['Parent2'])) {
        continue;
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

    $treePath = $rawPath . '/tree/' . $school['Parent'] . '/' . $school['Parent2'];
    if (!file_exists($treePath)) {
        mkdir($treePath, 0777, true);
    }
    $treeFile = $treePath . '/' . $school['Value'] . '.json';
    if (!file_exists($treeFile) || filesize($treeFile) === 0) {
        $meta = json_decode(file_get_contents($metaFile), true);
        if (!empty($meta['treeBound']['xmax'])) {
            file_put_contents($treeFile, file_get_contents("https://edutreemap.moe.edu.tw/trees_API/api/Map/GetPointGroup?n={$meta['treeBound']['ymax']}&w={$meta['treeBound']['xmin']}&s={$meta['treeBound']['ymin']}&e={$meta['treeBound']['xmax']}"));
        }
    }
    echo "{$treeFile} done\n";
}
