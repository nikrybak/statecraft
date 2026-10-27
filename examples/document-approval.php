<?php

declare(strict_types=1);

use Statecraft\Audit\Migrations;
use Statecraft\Audit\PdoAuditTrail;
use Statecraft\Definition\Definition;
use Statecraft\Dumper\GraphvizDumper;
use Statecraft\Event\TransitionCompleted;
use Statecraft\Guard\GuardResult;
use Statecraft\Machine;
use Statecraft\Marking\MethodMarkingStore;
use Statecraft\TransitionContext;

require __DIR__ . '/../vendor/autoload.php';

/*
 * A workflow: after submission the legal and the technical review run in parallel,
 * so two places are marked at once. Publishing is a join that needs both approvals.
 */
$definition = Definition::workflow('document_approval')
    ->initial('draft')
    ->places('legal_review', 'tech_review', 'legal_approved', 'tech_approved')
    ->final('published', 'rejected')
    ->transition('submit', 'draft', ['legal_review', 'tech_review'])
    ->transition('approve_legal', 'legal_review', 'legal_approved', guard: static fn(TransitionContext $c): GuardResult => GuardResult::when($c->actor === 'legal', 'only the legal team approves legal review'))
    ->transition('approve_tech', 'tech_review', 'tech_approved')
    ->transition('reject', ['legal_review', 'tech_review'], 'rejected')
    ->transition('publish', ['legal_approved', 'tech_approved'], 'published')
    ->build();

final class Document
{
    /** @var list<string> */
    private array $places = [];

    public function __construct(public readonly string $id) {}

    /**
     * @return list<string>
     */
    public function getPlaces(): array
    {
        return $this->places;
    }

    /**
     * @param list<string> $places
     */
    public function setPlaces(array $places): void
    {
        $this->places = $places;
    }
}

$pdo = new PDO('sqlite::memory:');
Migrations::apply($pdo);

$dispatcher = new class implements Psr\EventDispatcher\EventDispatcherInterface {
    public function dispatch(object $event): object
    {
        if ($event instanceof TransitionCompleted) {
            printf("[event] %s: [%s] -> [%s]\n", $event->transition()->name, $event->markingBefore()->toString(), $event->markingAfter->toString());
        }

        return $event;
    }
};

$machine = new Machine($definition, new MethodMarkingStore('getPlaces', 'setPlaces'), $dispatcher, audit: new PdoAuditTrail($pdo));
$document = new Document('doc-7');

$machine->apply($document, 'submit', actor: 'author');
$machine->apply($document, 'approve_tech', actor: 'cto');
echo 'Can publish yet? ', $machine->can($document, 'publish') ? 'yes' : 'no', "\n";
echo 'Can engineering approve legal? ', $machine->can($document, 'approve_legal', actor: 'cto') ? 'yes' : 'no', "\n";
$machine->apply($document, 'approve_legal', actor: 'legal');
$machine->apply($document, 'publish', actor: 'editor');

echo 'Finished: ', $machine->isFinished($document) ? 'yes' : 'no', "\n";
echo 'Rows in audit table: ', count((new PdoAuditTrail($pdo))->history('document_approval', 'doc-7')), "\n\n";

echo (new GraphvizDumper())->dump($definition);
