<?php

declare (strict_types=1);
namespace Sylius_Labs\Association_Hydrator;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Symfony\Component\Property_Access\Property_Access;
use Symfony\Component\Property_Access\Property_Accessor;
final class Association_Hydrator
{
    /** @var Entity_Manager_Interface The Doctrine entity manager used to build and execute hydration queries */
    private $entity_manager;
    /** @var Class_Metadata Class metadata for the root subject entity */
    private $class_metadata;
    /** @var Property_Accessor Symfony property accessor for reading intermediate association values */
    private $property_accessor;

    /**
     * Constructs a new hydrator bound to the given entity class metadata.
     *
     * @param Entity_Manager_Interface $entity_manager    The Doctrine entity manager.
     * @param Class_Metadata           $class_metadata    Metadata for the root entity whose associations will be hydrated.
     * @param ?Property_Accessor       $property_accessor Optional custom property accessor; a default one is created when omitted.
     */
    public function __construct(Entity_Manager_Interface $entity_manager, Class_Metadata $class_metadata, ?Property_Accessor $property_accessor = null)
    {
        $this->entity_manager = $entity_manager;
        $this->class_metadata = $class_metadata;
        $this->property_accessor = $property_accessor ?? Property_Access::create_property_accessor();
    }

    /**
     * Hydrates multiple association paths on a collection of subject entities.
     *
     * Iterates over each path and calls {@see hydrate_association()} for each one.
     * Use this when you need to warm several associations in a single logical step.
     *
     * @param object|object[]|\Doctrine\Common\Collections\Collection<int,object> $subjects         One or more root entities to hydrate.
     * @param iterable<string>                                                    $associations_paths Dot-notation paths, e.g. ['items', 'items.product'].
     *
     * @return void
     *
     * @complexity O(p * n) where p = number of paths and n = number of subjects
     */
    public function hydrate_associations($subjects, iterable $associations_paths): void
    {
        foreach ($associations_paths as $association_path) {
            $this->hydrate_association($subjects, $association_path);
        }
    }

    /**
     * Hydrates a single dot-notation association path on a collection of entities.
     *
     * Issues a single LEFT JOIN query using PARTIAL SELECT so that the Doctrine
     * Unit of Work registers the associated objects without re-fetching the root
     * entity's scalar fields.  Supports nested paths such as 'items.product'.
     *
     * @param object|object[]|\Doctrine\Common\Collections\Collection<int,object> $subjects         One or more root entities; a single object is wrapped in an array.
     * @param string                                                               $association_path Dot-notation path to the target association.
     *
     * @return void
     *
     * @complexity O(n) database round-trips: always exactly one query regardless of collection size
     */
    public function hydrate_association($subjects, string $association_path): void
    {
        if ([] === $subjects = $this->normalize_subject($subjects)) {
            return;
        }
        $initial_associations = explode('.', $association_path);
        $final_association = array_pop($initial_associations);
        $class_metadata = $this->class_metadata;
        foreach ($initial_associations as $initial_association) {
            $subjects = array_reduce($subjects, function (array $accumulator, $subject) use ($initial_association): array {
                $subject = $this->property_accessor->get_value($subject, $initial_association);
                return array_merge($accumulator, $this->normalize_subject($subject));
            }, []);
            if ([] === $subjects) {
                return;
            }
            $class_metadata = $this->entity_manager->get_class_metadata($class_metadata->get_association_target_class($initial_association));
        }
        $subjects = array_map([$this->entity_manager->get_unit_of_work(), 'getEntityIdentifier'], $subjects);
        $this->entity_manager->create_query_builder()->select('PARTIAL subject.{id}')->add_select('associations')->from($class_metadata->name, 'subject')->left_join(sprintf('subject.%s', $final_association), 'associations')->where('subject IN (:subjects)')->set_parameter('subjects', array_unique($subjects, \SORT_REGULAR))->get_query()->get_result();
    }
    /**
     * Normalises the subject argument to a plain PHP array of non-null objects.
     *
     * Accepts a single entity object, a plain array, or a Doctrine Collection
     * and always returns a flat, filtered (no nulls) array suitable for query
     * construction.
     *
     * @param object|object[]|\Doctrine\Common\Collections\Collection<int,object>|null $subject The value to normalise.
     *
     * @return list<object> A flat array of non-null entity objects.
     */
    private function normalize_subject($subject): array
    {
        if ($subject instanceof Collection) {
            $subject = $subject->to_array();
        }
        if (!is_array($subject)) {
            $subject = [$subject];
        }
        return \array_filter($subject);
    }
}