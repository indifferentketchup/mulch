<?php
use IndifferentKetchup\Iblogs\Config\Config;use IndifferentKetchup\Iblogs\Config\ConfigKey;use IndifferentKetchup\Iblogs\Util\URL;

$imprintUrl = Config::getInstance()->get(ConfigKey::LEGAL_IMPRINT);
$privacyUrl = Config::getInstance()->get(ConfigKey::LEGAL_PRIVACY);
?>
<footer>
    <?php if($imprintUrl || $privacyUrl): ?>
    <nav class="legal">
        <?php if ($imprintUrl): ?>
            <a href="<?=htmlspecialchars($imprintUrl); ?>" class="footer-link" title="Imprint" target="_blank">Imprint</a>
        <?php endif; ?>
        <?php if ($imprintUrl && $privacyUrl): ?>
            <span class="footer-separator"> - </span>
        <?php endif; ?>
        <?php if ($privacyUrl): ?>
            <a href="<?=htmlspecialchars($privacyUrl); ?>" class="footer-link" title="Privacy Policy" target="_blank">Privacy Policy</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <nav class="footer-nav">
        <a href="https://github.com/indifferentketchup/iblogs" title="iblogs on Github" target="_blank"><i class="fa-brands fa-github"></i>GitHub</a>
        <a href="<?=htmlspecialchars(URL::getApi()->toString()); ?>" title="iblogs API"><i class="fa-solid fa-code"></i>API</a>
    </nav>
    <span class="footer-text">based on <a href="https://github.com/aternosorg/mclogs" target="_blank" title="Original mclogs project">mclogs</a> by <a href="https://github.com/aternosorg" target="_blank" title="Aternos on GitHub"><i class="fa-brands fa-github"></i> Aternos</a>
    </span>
</footer>
