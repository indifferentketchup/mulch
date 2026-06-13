<?php

use IndifferentKetchup\Iblogs\Frontend\Assets\AssetLoader;
use IndifferentKetchup\Iblogs\Frontend\Assets\AssetType;
use IndifferentKetchup\Iblogs\Log;
use IndifferentKetchup\Iblogs\Config\Config;
use IndifferentKetchup\Iblogs\Config\ConfigKey;
use IndifferentKetchup\Iblogs\Frontend\Settings\Setting;
use IndifferentKetchup\Iblogs\Frontend\Settings\Settings;
use IndifferentKetchup\Iblogs\Util\TimeInterval;
use IndifferentKetchup\CodexPz\Analysis\AttributionConfidence;
use IndifferentKetchup\CodexPz\Analysis\CauseChainInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\EngineNoiseInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\ModAttributedInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineVersionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ModLoadInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\MissingIconInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\MissingThumpSoundInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\SpriteConfigInvalidInformation;

/** @var Log $log */

$settings = new Settings();

$allInformation = $log->getAnalysis()?->getInformation() ?? [];
$modLoadItems = [];
$assetWarningItems = [];
$otherInfo = [];
$engineVersionInfo = null;
$buildHash = null;
foreach ($allInformation as $info) {
    if ($info instanceof EngineVersionInformation) {
        $engineVersionInfo = $info;
        if (preg_match('/build\s+([a-f0-9]+)/i', $info->getValue(), $bm)) {
            $buildHash = substr($bm[1], 0, 12);
        }
    } elseif ($info instanceof ModLoadInformation) {
        $modLoadItems[] = $info;
    } elseif ($info instanceof MissingIconInformation || $info instanceof MissingThumpSoundInformation || $info instanceof SpriteConfigInvalidInformation) {
        $assetWarningItems[] = $info;
    } else {
        $otherInfo[] = $info;
    }
}
if ($engineVersionInfo !== null) {
    $otherInfo[] = $engineVersionInfo;
}
$assetWarningGroups = [];
foreach ($assetWarningItems as $item) {
    $class = get_class($item);
    if (!isset($assetWarningGroups[$class])) {
        $assetWarningGroups[$class] = [
            'label' => $item->getLabel(),
            'count' => 0,
            'severity' => $item instanceof SeverityAwareInsightInterface ? $item->getSeverity() : Severity::Low,
            'isNoise' => $item instanceof EngineNoiseInsightInterface,
        ];
    }
    $assetWarningGroups[$class]['count']++;
}
?><!DOCTYPE html>
<html lang="en">
    <head>
        <?php include __DIR__ . '/parts/head.php'; ?>
        <title><?=htmlspecialchars($log->getPageTitle()); ?></title>
        <meta name="description" content="<?=htmlspecialchars($log->getPageDescription()); ?>" />
    </head>
    <body class="log-body<?=$settings->getBodyClassesString(); ?>">
    <?php include __DIR__ . '/parts/header.php'; ?>
            <main>
                <div class="log-header">
                   <div class="log-header-inner">
                       <div class="left">
                           <div class="log-title">
                               <h1>
                                   <i class="fas fa-file-lines"></i>
                                   <?=htmlspecialchars($log->getCodexLog()->getTitle()); ?>
                               </h1>
                               <div class="log-url-group">
                                   <button class="log-url-btn" data-clipboard="<?=htmlspecialchars($log->getURL()->toString()); ?>" title="Copy log URL to clipboard">
                                       <span class="log-url"><?=htmlspecialchars($log->getDisplayURL()); ?></span>
                                       <i class="fa-solid fa-copy"></i>
                                   </button>
                                   <?php $created = $log->getCreated()?->toDateTime()->getTimestamp(); ?>
                                   <?php if ($created): ?>
                                       <div class="log-created-inline" title="Uploaded">
                                           <i class="fa-solid fa-clock"></i>
                                           <span class="created" data-time="<?=htmlspecialchars($created); ?>"></span>
                                       </div>
                                   <?php endif; ?>
                               </div>
                           </div>
                       </div>
                       <div class="right">
                           <div class="details">
                               <div class="log-info-actions">
                                   <?php if($log->hasErrors()): ?>
                                       <div class="btn btn-danger btn-small" id="error-toggle">
                                           <i class="fa fa-exclamation-circle"></i>
                                           <?=htmlspecialchars($log->getErrorsString()); ?>
                                       </div>
                                   <?php endif; ?>
                                   <div class="btn btn-dark btn-small" id="down-button">
                                       <i class="fa fa-arrow-circle-down"></i>
                                       <?=htmlspecialchars($log->getLinesString()); ?>
                                   </div>
                                   <a class="btn btn-dark btn-small" id="raw" target="_blank" title="Raw log" href="<?=$log->getRawURL()->toString(); ?>">
                                       <i class="fa fa-arrow-up-right-from-square"></i>
                                       Raw
                                   </a>
                               </div>
                               <?php if ($buildHash): ?>
                                   <div class="log-build-info">
                                       Build: <span class="log-build-hash"><?=htmlspecialchars($buildHash); ?></span>
                                   </div>
                               <?php endif; ?>
                           </div>
                       </div>
                   </div>
                   <?php if(count($log->getVisibleMetadata()) > 0 || count($otherInfo) > 0 || count($modLoadItems) > 0): ?>
                       <div class="log-info-rows">
                           <?php if(count($log->getVisibleMetadata()) > 0): ?>
                               <div class="log-info-row">
                                   <div class="info-row-items">
                                       <div class="info-row-header">
                                           <i class="fa-solid fa-tags"></i>
                                           <span>Metadata</span>
                                       </div>
                                       <?php foreach($log->getVisibleMetadata() as $metadata): ?>
                                           <span class="info-item">
                                               <span class="info-label"><?=htmlspecialchars($metadata->getDisplayLabel()); ?>:</span>
                                               <span class="info-value"><?=htmlspecialchars($metadata->getDisplayValue()); ?></span>
                                           </span>
                                       <?php endforeach; ?>
                                   </div>
                               </div>
                           <?php endif; ?>
                           <?php if(count($otherInfo) > 0 || count($modLoadItems) > 0): ?>
                               <div class="log-info-row">
                                   <div class="info-row-items">
                                       <div class="info-row-header">
                                           <i class="fa-solid fa-cube"></i>
                                           <span>Detected</span>
                                       </div>
                                       <?php foreach($otherInfo as $info): ?>
                                           <span class="info-item">
                                               <span class="info-label"><?=htmlspecialchars($info->getLabel()); ?>:</span>
                                               <span class="info-value"><?=htmlspecialchars($info->getValue()); ?></span>
                                           </span>
                                       <?php endforeach; ?>
                                       <?php if(count($modLoadItems) > 0): ?>
                                           <details class="mods-collapsible">
                                               <summary>
                                                   <i class="fa-solid fa-puzzle-piece"></i>
                                                   Mods loaded
                                                   <span class="mods-count">(<?= count($modLoadItems); ?>)</span>
                                               </summary>
                                               <div class="mods-list">
                                                   <?php foreach($modLoadItems as $mod): ?>
                                                       <span class="mod-name"><?=htmlspecialchars($mod->getValue()); ?></span>
                                                   <?php endforeach; ?>
                                               </div>
                                           </details>
                                       <?php endif; ?>
                                   </div>
                               </div>
                           <?php endif; ?>
                       </div>
                   <?php endif; ?>
                    <?php
                    $problems = $log->getAnalysis()?->getProblems() ?? [];
                    usort($problems, function ($a, $b) {
                        $aScore = ($a instanceof SeverityAwareInsightInterface ? $a->getSeverity()->value : Severity::Medium->value) * $a->getCounterValue();
                        $bScore = ($b instanceof SeverityAwareInsightInterface ? $b->getSeverity()->value : Severity::Medium->value) * $b->getCounterValue();
                        return $bScore <=> $aScore;
                    });
                    $hideEngineNoise = $settings->get(Setting::HIDE_ENGINE_NOISE);
                    $visibleCount = $hideEngineNoise
                        ? count(array_filter($problems, fn($p) => !($p instanceof EngineNoiseInsightInterface)))
                        : count($problems);
                    $noiseCount = count($problems) - $visibleCount;
                    $visibleAssetGroupCount = $hideEngineNoise
                        ? count(array_filter($assetWarningGroups, fn($g) => !$g['isNoise']))
                        : count($assetWarningGroups);
                    $hiddenAssetGroupCount = count($assetWarningGroups) - $visibleAssetGroupCount;
                    $totalVisibleCount = $visibleCount + $visibleAssetGroupCount;
                    $totalNoiseCount = $noiseCount + $hiddenAssetGroupCount;
                    ?>
                    <?php if (count($problems) > 0 || count($assetWarningGroups) > 0): ?>
                        <div class="problems-panel-container">
                            <div class="problems-panel">
                                <div class="problems-header">
                                    <span class="problems-count"><?= $totalVisibleCount; ?></span>
                                    <span class="problems-title">
                                        <?= $totalVisibleCount === 1 ? 'Problem' : 'Problems'; ?> detected<?php if ($totalNoiseCount > 0): ?> <span class="problems-noise-count">(<?= $totalNoiseCount; ?> noise hidden)</span><?php endif; ?>
                                    </span>
                                </div>
                                <div class="problems-list">
                                    <?php foreach ($problems as $problem): ?>
                                        <?php
                                        $severity = $problem instanceof SeverityAwareInsightInterface ? $problem->getSeverity() : Severity::Medium;
                                        $isEngineNoise = $problem instanceof EngineNoiseInsightInterface;
                                        $severityIcon = match ($severity) {
                                            Severity::Critical => 'fa-skull-crossbones',
                                            Severity::High     => 'fa-triangle-exclamation',
                                            Severity::Medium   => 'fa-flag',
                                            Severity::Low      => 'fa-circle-info',
                                            Severity::Noise    => 'fa-volume-xmark',
                                        };
                                        $entry = $problem->getEntry();
                                        $entryLine = null;
                                        if ($entry !== null) {
                                            $entryLines = $entry->getLines();
                                            $entryLine = reset($entryLines) ?: null;
                                        }
                                        $entryLineNumber = $entryLine?->getNumber();
                                        $rowClasses = 'problem-item severity-' . strtolower($severity->name) . ($isEngineNoise ? ' engine-noise' : '');
                                        ?>
                                        <div class="<?= htmlspecialchars($rowClasses); ?>" aria-label="Severity: <?= htmlspecialchars($severity->name); ?>">
                                            <?php if ($entryLineNumber !== null): ?>
                                                <a href="/<?= htmlspecialchars($log->getId()->get()); ?>#L<?= (int)$entryLineNumber; ?>" class="problem-entry" onclick="updateLineNumber('#L<?= (int)$entryLineNumber; ?>');">
                                            <?php else: ?>
                                                <div class="problem-entry">
                                            <?php endif; ?>
                                                <span class="problem-severity problem-label" aria-label="<?= htmlspecialchars($severity->name); ?>">
                                                    <i class="fa-solid <?= $severityIcon; ?>"></i>
                                                    <?= htmlspecialchars($severity->name); ?>
                                                </span>
                                                <span class="problem-text"><?= htmlspecialchars($problem->getMessage()); ?></span>
                                                <?php if ($entryLineNumber !== null): ?>
                                                    <span class="problem-line">Line <?= (int)$entryLineNumber; ?></span>
                                                <?php endif; ?>
                                                <?php if ($problem->getCounterValue() > 1): ?>
                                                    <span class="problem-counter" aria-label="<?= number_format($problem->getCounterValue()); ?> occurrences">×<?= number_format($problem->getCounterValue()); ?></span>
                                                <?php endif; ?>
                                            <?php if ($entryLineNumber !== null): ?>
                                                </a>
                                            <?php else: ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($problem instanceof ModAttributedInsightInterface): ?>
                                                <?php $mod = $problem->getModAttribution(); ?>
                                                <?php if ($mod !== null): ?>
                                                    <?php $isDirect = $mod->confidence === AttributionConfidence::Direct; ?>
                                                    <?php if ($mod->workshopId !== null): ?>
                                                        <a class="problem-mod-tag<?= !$isDirect ? ' problem-mod-tag--inferred' : ''; ?>"
                                                           href="https://steamcommunity.com/sharedfiles/filedetails/?id=<?= htmlspecialchars($mod->workshopId); ?>"
                                                           target="_blank" rel="noopener"<?= !$isDirect ? ' data-confidence="' . htmlspecialchars($mod->confidence->value) . '"' : ''; ?>>
                                                            <i class="fa-brands fa-steam"></i>
                                                            <?= htmlspecialchars($mod->modName); ?><?php if (!$isDirect): ?> <span class="problem-mod-confidence">inferred</span><?php endif; ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="problem-mod-tag<?= !$isDirect ? ' problem-mod-tag--inferred' : ''; ?>"<?= !$isDirect ? ' data-confidence="' . htmlspecialchars($mod->confidence->value) . '"' : ''; ?>>
                                                            <?= htmlspecialchars($mod->modName); ?><?php if (!$isDirect): ?> <span class="problem-mod-confidence">inferred</span><?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($problem instanceof CauseChainInsightInterface): ?>
                                                <?php $causeChain = $problem->getCauseChain(); ?>
                                                <?php if ($causeChain !== null && $causeChain !== ''): ?>
                                                    <details class="problem-stack">
                                                        <summary>Stack trace</summary>
                                                        <pre><?= htmlspecialchars($causeChain); ?></pre>
                                                    </details>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (count($problem->getSolutions()) > 0): ?>
                                                <details class="problem-solutions">
                                                    <summary><?= count($problem->getSolutions()) === 1 ? 'Solution' : 'Solutions'; ?></summary>
                                                    <?php foreach ($problem->getSolutions() as $solution): ?>
                                                        <div class="problem-solution">
                                                            <i class="fa-solid fa-lightbulb"></i>
                                                            <span><?= preg_replace("/'([^']+)'/", "'<strong>$1</strong>'", htmlspecialchars($solution->getMessage())); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </details>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php foreach ($assetWarningGroups as $group):
                                        if ($hideEngineNoise && $group['isNoise']) continue;
                                        $severity = $group['severity'];
                                        $severityIcon = match ($severity) {
                                            Severity::Critical => 'fa-skull-crossbones',
                                            Severity::High     => 'fa-triangle-exclamation',
                                            Severity::Medium   => 'fa-flag',
                                            Severity::Low      => 'fa-circle-info',
                                            Severity::Noise    => 'fa-volume-xmark',
                                        };
                                        $rowClasses = 'problem-item severity-' . strtolower($severity->name) . ($group['isNoise'] ? ' engine-noise' : '');
                                    ?>
                                        <div class="<?= htmlspecialchars($rowClasses); ?>" aria-label="Severity: <?= htmlspecialchars($severity->name); ?>">
                                            <div class="problem-entry">
                                                <span class="problem-severity problem-label" aria-label="<?= htmlspecialchars($severity->name); ?>">
                                                    <i class="fa-solid <?= $severityIcon; ?>"></i>
                                                    <?= htmlspecialchars($severity->name); ?>
                                                </span>
                                                <span class="problem-text"><?= htmlspecialchars($group['label']); ?></span>
                                                <?php if ($group['count'] > 1): ?>
                                                    <span class="problem-counter" aria-label="<?= number_format($group['count']); ?> occurrences">×<?= number_format($group['count']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
            <div class="log-container">
                <div class="log">
                    <?php
                    echo $log->getPrinter()->print();
                    ?>
                </div>
            </div>
            <div class="log-footer">
                <div class="log-bottom">
                    <div class="btn btn-small btn-dark" id="up-button" title="Scroll to top">
                        <i class="fa fa-arrow-circle-up"></i>
                    </div>
                    <div class="actions">
                        <?php if ($log->hasValidTokenCookie()): ?>
                        <div class="delete-wrapper popover-wrapper">
                            <button class="delete-trigger popover-trigger btn btn-small btn-danger" title="Delete log" popovertarget="delete-overlay">
                                <i class="fa-solid fa-trash"></i>
                                Delete
                            </button>
                            <div class="delete-overlay popover-content popover-danger" id="delete-overlay" popover>
                                <span class="delete-message">Delete this log permanently?</span>
                                <div class="popover-error">

                                </div>
                                <div class="delete-actions">
                                    <button class="btn btn-small btn-white" popovertarget="delete-overlay">Cancel</button>
                                    <button class="btn btn-small btn-danger delete-log-button">Delete</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="settings-dropdown popover-wrapper">
                            <button class="settings-trigger popover-trigger btn btn-small btn-dark" title="Settings" popovertarget="settings-overlay">
                                <i class="fas fa-cog"></i>
                                Settings
                            </button>
                            <div class="settings-overlay popover-content" id="settings-overlay" popover>
                                <?php $currentGroup = null; ?>
                                <?php foreach(Setting::cases() as $setting): ?>
                                    <?php $group = $setting->getGroup(); ?>
                                    <?php if ($group !== $currentGroup): ?>
                                        <?php if ($currentGroup !== null): ?>
                                            <div class="settings-group-divider"></div>
                                        <?php endif; ?>
                                        <div class="settings-group-label"><?=htmlspecialchars($group); ?></div>
                                        <?php $currentGroup = $group; ?>
                                    <?php endif; ?>
                                    <label class="setting" for="setting-<?=$setting->value; ?>">
                                        <span class="setting-label"><?=$setting->getLabel(); ?></span>
                                        <input type="checkbox"
                                               id="setting-<?=$setting->value; ?>"
                                               class="setting-checkbox"
                                               data-body-class="<?=$setting->getBodyClass() ?? ""; ?>"
                                               data-key="<?=$setting->value; ?>"
                                                <?=($settings->get($setting)) ? " checked" : ""; ?>/>
                                    </label>
                                    <span class="setting-description"><?=$setting->getDescription(); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="log-details">
                <?php $source = $log->getSource(); ?>
                <?php if ($source): ?>
                    <div class="meta-data">
                        <div class="source" title="Source">
                            <i class="fa-solid fa-arrow-up-from-bracket"></i>
                            <?=htmlspecialchars($source); ?>
                        </div>
                    </div>
                <?php endif; ?>
                    <div class="delete-notice">
                        This log will be saved for <?= htmlspecialchars(TimeInterval::getInstance()->format(Config::getInstance()->get(ConfigKey::STORAGE_TTL))); ?> from its last view.
                    </div>
                    <?php if ($abuseEmail = Config::getInstance()->get(ConfigKey::LEGAL_ABUSE)): ?>
                        <a href="mailto:<?=htmlspecialchars($abuseEmail); ?>?subject=Report%20<?=htmlspecialchars(rawurlencode(Config::getInstance()->getName())); ?>/<?=htmlspecialchars($log->getId()->get()); ?>" class="report-link">
                            <i class="fa-solid fa-flag"></i>
                            Report abuse
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php include __DIR__ . '/parts/footer.php'; ?>
        <div class="floating-scrollbar-container">
            <div class="floating-scrollbar">
                <div class="floating-scrollbar-content">
                </div>
            </div>
        </div>
        <?= AssetLoader::getInstance()->getHTML(AssetType::JS, "js/log.js"); ?>
    </body>
</html>
