<?php
$tenantSlug = isset($_GET["tenant"]) ? preg_replace("/[^a-zA-Z0-9-_]/", "", $_GET["tenant"]) : "default";
$tenantPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "tenants" . DIRECTORY_SEPARATOR . $tenantSlug . DIRECTORY_SEPARATOR . "data.json";

if (!file_exists($tenantPath)) {
  $tenantSlug = "default";
  $tenantPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "tenants" . DIRECTORY_SEPARATOR . $tenantSlug . DIRECTORY_SEPARATOR . "data.json";
}

$tenantData = [];
if (file_exists($tenantPath)) {
  $json = file_get_contents($tenantPath);
  $decoded = json_decode($json, true);
  if (is_array($decoded)) {
    $tenantData = $decoded;
  }
}

if (empty($tenantData)) {
  $tenantData = [
    "tenant" => ["slug" => "default"],
    "brand" => ["name" => "TVirtualGaming"],
    "banners" => [],
    "featured" => [],
    "games" => []
  ];
}

$brandName = $tenantData["brand"]["name"] ?? "TVirtualGaming";
$games = $tenantData["games"] ?? [];
$gamesBySlug = [];
$popularGames = [];
$moreGames = [];

foreach ($games as $game) {
  if (!isset($game["slug"])) {
    continue;
  }
  $gamesBySlug[$game["slug"]] = $game;
  if (!empty($game["popular"])) {
    $popularGames[] = $game;
  } else {
    $moreGames[] = $game;
  }
}

$tenantData["gamesBySlug"] = $gamesBySlug;
$tenantData["popularGames"] = $popularGames;
$tenantData["moreGames"] = $moreGames;
$tenantData["tenant"]["slug"] = $tenantSlug;
