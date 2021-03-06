<?php

declare(strict_types=1);

namespace Rector\CakePHP\Rector\MethodCall;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use Rector\CakePHP\ValueObject\RenameMethodCallBasedOnParameter;
use Rector\Core\Contract\Rector\ConfigurableRectorInterface;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/**
 * @see https://book.cakephp.org/4.0/en/appendices/4-0-migration-guide.html
 * @see https://github.com/cakephp/cakephp/commit/77017145961bb697b4256040b947029259f66a9b
 *
 * @see \Rector\CakePHP\Tests\Rector\MethodCall\RenameMethodCallBasedOnParameterRector\RenameMethodCallBasedOnParameterRectorTest
 */
final class RenameMethodCallBasedOnParameterRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var string
     */
    public const CALLS_WITH_PARAM_RENAMES = 'calls_with_param_renames';

    /**
     * @var RenameMethodCallBasedOnParameter[]
     */
    private $callsWithParamRenames = [];

    public function getRuleDefinition(): RuleDefinition
    {
        $configuration = [
            self::CALLS_WITH_PARAM_RENAMES => [
                new RenameMethodCallBasedOnParameter('ServerRequest', 'getParam', 'paging', 'getAttribute'),
                new RenameMethodCallBasedOnParameter('ServerRequest', 'withParam', 'paging', 'withAttribute'),
            ],
        ];
        return new RuleDefinition(
            'Changes method calls based on matching the first parameter value.',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
$object = new ServerRequest();

$config = $object->getParam('paging');
$object = $object->withParam('paging', ['a value']);
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
$object = new ServerRequest();

$config = $object->getAttribute('paging');
$object = $object->withAttribute('paging', ['a value']);
CODE_SAMPLE
                    ,
                    $configuration
                ),
            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $node
     */
    public function refactor(Node $node): ?Node
    {
        $callWithParamRename = $this->matchTypeAndMethodName($node);
        if ($callWithParamRename === null) {
            return null;
        }

        $node->name = new Identifier($callWithParamRename->getNewMethod());

        return $node;
    }

    public function configure(array $configuration): void
    {
        $callsWithParamNames = $configuration[self::CALLS_WITH_PARAM_RENAMES] ?? [];
        Assert::allIsInstanceOf($callsWithParamNames, RenameMethodCallBasedOnParameter::class);
        $this->callsWithParamRenames = $callsWithParamNames;
    }

    private function matchTypeAndMethodName(MethodCall $methodCall): ?RenameMethodCallBasedOnParameter
    {
        foreach ($this->callsWithParamRenames as $callWithParamRename) {
            if (! $this->isObjectType($methodCall, $callWithParamRename->getOldClass())) {
                continue;
            }

            if (! $this->isName($methodCall->name, $callWithParamRename->getOldMethod())) {
                continue;
            }

            if (count($methodCall->args) < 1) {
                continue;
            }

            $arg = $methodCall->args[0];
            if (! $this->isValue($arg->value, $callWithParamRename->getParameterName())) {
                continue;
            }

            return $callWithParamRename;
        }

        return null;
    }
}
