<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Conditions;

use CraftCms\Cms\Condition\BaseSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use Illuminate\Database\Query\Builder;
use JustinHoltWeb\Bunting\Bunting;
use JustinHoltWeb\Bunting\Flags\Flags;
use Override;

use function CraftCms\Cms\t;

/**
 * “Feature flag is on” — for any element condition (field layout elements, sources,
 * relation fields, other plugins' rules). It ignores the element and asks whether the
 * chosen flag is on for the current request. Stored by flag UID, so renaming a flag
 * doesn't break it; a deleted flag counts as off.
 */
class FeatureActiveConditionRule extends BaseSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Feature flag is on', category: 'bunting');
    }

    #[Override]
    public function getGroupLabel(): ?string
    {
        return t('Bunting', category: 'bunting');
    }

    /** @return array<array-key, mixed> */
    protected function options(): array
    {
        $options = [];

        foreach (app(Flags::class)->all() as $flag) {
            $options[] = ['label' => $flag->name, 'value' => $flag->uid];
        }

        return $options;
    }

    public function matchElement(ElementInterface $element): bool
    {
        return $this->isOn();
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        if (! $this->isOn()) {
            $query->whereRaw('1 = 0');
        }
    }

    private function isOn(): bool
    {
        $flag = app(Flags::class)->getByUid($this->value);

        return $flag !== null && app(Bunting::class)->active($flag->handle);
    }
}
