# AssociationHydrator Architecture

## Purpose

A focused Doctrine ORM utility that eagerly loads entity associations in a single
optimised query, preventing N+1 query problems when iterating over collections.

## Directory Structure

```
src/
  Association_Hydrator.php   — the single public class
```

## Key Design Decisions

- **Single responsibility**: one class, one job — hydrate Doctrine associations.
- **Partial SELECT**: uses `PARTIAL subject.{id}` so that only the join is issued,
  leaving already-loaded scalar fields untouched in the Unit of Work.
- **Dot-notation paths**: e.g. `'order.items.product'` allows multi-level eager
  loading in one call, traversing intermediate associations automatically.
- **Nullable PropertyAccessor**: the constructor accepts an optional
  `Property_Accessor`; if omitted, one is created via `Property_Access::create_property_accessor()`.

## Extension Points

- Swap the `Entity_Manager_Interface` dependency to use a different ORM manager.
- Pass a custom `Property_Accessor` for non-standard property access strategies.

## Dependency Flow

```
Consumer → Association_Hydrator
               ├── EntityManagerInterface  (Doctrine ORM)
               ├── ClassMetadata           (Doctrine Persistence)
               └── PropertyAccessor        (Symfony PropertyAccess)
```
