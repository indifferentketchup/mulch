<?php

require_once __DIR__ . '/codex-pz/vendor/autoload.php';

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Detective\Minecraft\MinecraftDetective;
use IndifferentKetchup\CodexPz\Detective\Hytale\HytaleDetective;
use IndifferentKetchup\CodexPz\Detective\ProjectZomboid\ProjectZomboidDetective;
use IndifferentKetchup\CodexPz\Log\AnalysableLogInterface;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use IndifferentKetchup\CodexPz\Analysis\EngineNoiseInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\ModAttributedInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\CauseChainInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\AttributionConfidence as ModConfidence;

header('Content-Type: application/json; charset=utf-8');
header('X-Accel-Buffering: no');

$content = file_get_contents('php://input');
if (empty($content)) {
    $content = file_get_contents('php://stdin');
}
if (empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

$detective = new Detective();
$detective->addDetective(new MinecraftDetective())
          ->addDetective(new HytaleDetective())
          ->addDetective(new ProjectZomboidDetective());

$detective->setLogFile(new StringLogFile($content));
$log = $detective->detect();
$log->parse();

$game = match (true) {
    $log instanceof \IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidLog => 'ProjectZomboid',
    $log instanceof \IndifferentKetchup\CodexPz\Log\Minecraft\MinecraftLog => 'Minecraft',
    $log instanceof \IndifferentKetchup\CodexPz\Log\Hytale\HytaleLog => 'Hytale',
    default => 'Generic',
};

$entries = [];
$errorCount = 0;

foreach ($log as $entry) {
    $level = $entry->getLevel();
    $entryData = [
        'level' => $level->asString(),
        'level_int' => $level->asInt(),
        'prefix' => $entry->getPrefix(),
        'time' => null,
        'lines' => [],
    ];
    foreach ($entry as $line) {
        $entryData['lines'][] = [
            'number' => $line->getNumber(),
            'text' => $line->getText(),
        ];
    }
    $entries[] = $entryData;
    if ($level->asInt() <= \IndifferentKetchup\CodexPz\Log\Level::ERROR->asInt()) {
        $errorCount++;
    }
}

$analysisData = null;

if ($log instanceof AnalysableLogInterface) {
    $analysis = $log->analyse();
    $problems = $analysis->getProblems() ?? [];
    $information = $analysis->getInformation() ?? [];

    $problemList = [];
    foreach ($problems as $problem) {
        $severity = $problem instanceof SeverityAwareInsightInterface
            ? $problem->getSeverity()->name
            : 'Medium';

        $entryLine = null;
        $entryObj = $problem->getEntry();
        if ($entryObj !== null) {
            $entryLines = $entryObj->getLines();
            if (!empty($entryLines)) {
                $entryLine = reset($entryLines)->getNumber();
            }
        }

        $prob = [
            'message' => $problem->getMessage(),
            'severity' => $severity,
            'count' => $problem->getCounterValue(),
            'entry_line' => $entryLine,
            'is_noise' => $problem instanceof EngineNoiseInsightInterface,
            'solutions' => [],
        ];

        if ($problem instanceof CauseChainInsightInterface) {
            $prob['stack_trace'] = $problem->getCauseChain();
        }

        foreach ($problem->getSolutions() as $solution) {
            $prob['solutions'][] = ['message' => $solution->getMessage()];
        }

        if ($problem instanceof ModAttributedInsightInterface) {
            $mod = $problem->getModAttribution();
            if ($mod !== null) {
                $prob['mod'] = [
                    'name' => $mod->modName,
                    'workshop_id' => $mod->workshopId,
                    'confidence' => $mod->confidence->value,
                    'is_direct' => $mod->confidence === ModConfidence::Direct,
                ];
            }
        }

        $problemList[] = $prob;
    }

    $infoList = [];
    foreach ($information as $info) {
        $infoList[] = [
            'label' => $info->getLabel(),
            'value' => $info->getValue(),
        ];
    }

    $analysisData = [
        'problems' => $problemList,
        'information' => $infoList,
    ];
}

echo json_encode([
    'detected' => $game,
    'title' => $log->getTitle(),
    'lines' => count($entries),
    'errors' => $errorCount,
    'entries' => $entries,
    'analysis' => $analysisData,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
