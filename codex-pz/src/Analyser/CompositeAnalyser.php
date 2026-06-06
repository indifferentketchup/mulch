<?php

namespace IndifferentKetchup\CodexPz\Analyser;

use IndifferentKetchup\CodexPz\Analysis\Analysis;
use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;
use IndifferentKetchup\CodexPz\Log\AnalysableLogInterface;

/**
 * Composes multiple AnalyserInterface children into a single merged Analysis.
 *
 * WARN-005: setLog() MUST propagate to every child. AnalysableLog::analyse()
 * calls setLog() only on the outermost analyser; without propagation each
 * child's $this->log is null and analyse() null-derefs when iterating the log.
 */
class CompositeAnalyser extends Analyser
{
    /** @var AnalyserInterface[] */
    private array $children;

    public function __construct(AnalyserInterface ...$children)
    {
        $this->children = $children;
    }

    public function setLog(AnalysableLogInterface $log): static
    {
        parent::setLog($log);
        foreach ($this->children as $child) {
            $child->setLog($log);
        }
        return $this;
    }

    public function analyse(): AnalysisInterface
    {
        $merged = new Analysis();
        $merged->setLog($this->log);

        foreach ($this->children as $child) {
            foreach ($child->analyse()->getInsights() as $insight) {
                $merged->addInsight($insight);
            }
        }

        return $merged;
    }
}
