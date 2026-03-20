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
    /** @var EntityManagerInterface */
    private $entity_manager;
    /** @var ClassMetadata */
    private $class_metadata;
    /** @var PropertyAccessor */
    private $property_accessor;
    public function __construct(Entity_Manager_Interface $entity_manager, Class_Metadata $class_metadata, ?Property_Accessor $property_accessor = null)
    {
        $this->entity_manager = $entity_manager;
        $this->class_metadata = $class_metadata;
        $this->property_accessor = $property_accessor ?? Property_Access::create_property_accessor();
    }
    /**
     * @param mixed $subjects
     * @param iterable|string[] $associationsPaths
     */
    public function hydrate_associations($subjects, iterable $associations_paths): void
    {
        foreach ($associations_paths as $association_path) {
            $this->hydrate_association($subjects, $association_path);
        }
    }
    /**
     * @param mixed $subjects
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
     * @param mixed $subject
     *
     * @return array|mixed[]
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