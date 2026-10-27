# Workflows and parallel states

statecraft supports two kinds of machine, selected with `MachineType`.

## State machine

Exactly one state is current at any time.

- A transition with several `from` states fires from **any one** of them (`cancel` from `pending` or `paid`).
- A transition has exactly one `to` state.
- There is exactly one initial state.

This is what most entities need: orders, invoices, subscriptions, tickets.

## Workflow

A workflow is a 1-safe Petri net: the marking is a **set** of places, and several can be marked
at the same time.

- A transition is enabled only when **all** of its `from` places are marked (a join).
- Firing removes all `from` places and marks all `to` places; several `to` places is a fork.
- There may be several initial places.
- A place is either marked or not; it cannot hold more than one token.

Use a workflow when independent tracks run in parallel and meet later: a document that needs both
a legal and a technical review before publication, an onboarding where IT setup and paperwork
happen in any order, a release that needs QA and security sign-off.

```php
$definition = Definition::workflow('document_approval')
    ->initial('draft')
    ->places('legal_review', 'tech_review', 'legal_approved', 'tech_approved')
    ->final('published', 'rejected')
    ->transition('submit', 'draft', ['legal_review', 'tech_review'])        // fork
    ->transition('approve_legal', 'legal_review', 'legal_approved')
    ->transition('approve_tech', 'tech_review', 'tech_approved')
    ->transition('publish', ['legal_approved', 'tech_approved'], 'published') // join
    ->transition('reject', ['legal_review', 'tech_review'], 'rejected')
    ->build();
```

After `submit` the marking is `[legal_review, tech_review]`. After `approve_tech` it is
`[legal_review, tech_approved]`; `publish` is still disabled because `legal_approved` is missing.

## Why not model it as a state machine?

With one current state you would need a state for every combination of review outcomes
(`legal_ok_tech_pending`, `legal_pending_tech_ok`, ...). The number grows multiplicatively with
every parallel track. A workflow keeps one place per fact and lets the join express "both".

## Storing a workflow marking

The marking stores write a list of place names for workflows, which fits a JSON column. When
reading they also accept a map of `place => true`, the shape Symfony Workflow uses, which makes
migrating existing data straightforward.

## Validation differences

Reachability for workflows treats a join as reachable once each of its inputs is reachable on its
own. That catches typos and forgotten transitions, but it is not a full proof that the inputs can
be marked at the same time; the random-walk property tests in `tests/Property` show how to check
such behaviour for your own definitions.
