<?php

namespace App\Tests\Unit\Form;

use App\Entity\Formation;
use App\Entity\PeriodPosition;
use App\Entity\Shift;
use App\Form\FormationOrNewType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;

/**
 * The "Formation" dropdown of a shift or of a typical week position also offers "Nouveau ★":
 * not a formation, but a restriction to the new beneficiaries.
 *
 * @internal
 */
class FormationOrNewTypeTest extends TestCase
{
    /** @var FormFactoryInterface */
    private $factory;

    /** @var Formation */
    private $cuisine;

    /** @var Formation */
    private $epicerie;

    protected function setUp(): void
    {
        $this->cuisine = $this->createFormation(5, 'Cuisine');
        $this->epicerie = $this->createFormation(3, 'Epicerie');

        $repository = $this->createMock(EntityRepository::class);
        // the formations are expected by name: Cuisine, then Epicerie
        $repository->method('findBy')->willReturn([$this->cuisine, $this->epicerie]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('find')->willReturnCallback(function ($class, $id) {
            return [3 => $this->epicerie, 5 => $this->cuisine][$id] ?? null;
        });

        $this->factory = Forms::createFormFactoryBuilder()
            ->addType(new FormationOrNewType($em, '★'))
            ->getFormFactory()
        ;
    }

    private function createFormation(int $id, string $name): Formation
    {
        $formation = $this->createMock(Formation::class);
        $formation->method('getId')->willReturn($id);
        $formation->method('getName')->willReturn($name);

        return $formation;
    }

    /**
     * @param PeriodPosition|Shift $entity
     */
    private function createForm($entity): FormInterface
    {
        return $this->factory
            ->createBuilder(FormType::class, $entity, ['data_class' => get_class($entity)])
            ->add('formation', FormationOrNewType::class)
            ->getForm()
        ;
    }

    public function testOffersNewFirstThenTheFormationsByName(): void
    {
        $choices = $this->createForm(new Shift())->createView()['formation']->vars['choices'];

        $this->assertSame(['new', '5', '3'], array_map(function ($choice) {
            return $choice->value;
        }, $choices));
        $this->assertSame(['Nouveau ★', 'Cuisine', 'Epicerie'], array_map(function ($choice) {
            return $choice->label;
        }, $choices));
    }

    /**
     * @dataProvider entities
     */
    public function testNothingIsSelectedWhenThereIsNoRestriction(callable $newEntity): void
    {
        $this->assertNull($this->createForm($newEntity())->get('formation')->getData());
    }

    /**
     * @dataProvider entities
     */
    public function testNewIsSelectedWhenTheEntityIsReservedToNewBeneficiaries(callable $newEntity): void
    {
        $entity = $newEntity();
        $entity->setNewBeneficiaryOnly(true);

        $this->assertSame('new', $this->createForm($entity)->get('formation')->getData());
    }

    /**
     * @dataProvider entities
     */
    public function testTheFormationOfTheEntityIsSelected(callable $newEntity): void
    {
        $entity = $newEntity();
        $entity->setFormation($this->cuisine);

        $this->assertSame('5', $this->createForm($entity)->get('formation')->getData());
    }

    /**
     * @dataProvider entities
     */
    public function testChoosingNewReservesTheEntityToNewBeneficiaries(callable $newEntity): void
    {
        $entity = $newEntity();
        $entity->setFormation($this->cuisine);

        $form = $this->createForm($entity);
        $form->submit(['formation' => 'new']);

        $this->assertTrue($entity->isNewBeneficiaryOnly());
        $this->assertNull($entity->getFormation());
    }

    /**
     * @dataProvider entities
     */
    public function testChoosingAFormationRemovesTheReservationToNewBeneficiaries(callable $newEntity): void
    {
        $entity = $newEntity();
        $entity->setNewBeneficiaryOnly(true);

        $form = $this->createForm($entity);
        $form->submit(['formation' => '3']);

        $this->assertFalse($entity->isNewBeneficiaryOnly());
        $this->assertSame($this->epicerie, $entity->getFormation());
    }

    /**
     * @dataProvider entities
     */
    public function testChoosingNothingRemovesEveryRestriction(callable $newEntity): void
    {
        $entity = $newEntity();
        $entity->setNewBeneficiaryOnly(true);

        $form = $this->createForm($entity);
        $form->submit(['formation' => '']);

        $this->assertFalse($entity->isNewBeneficiaryOnly());
        $this->assertNull($entity->getFormation());
    }

    /**
     * @dataProvider entities
     */
    public function testAnUnknownChoiceKeepsTheEntityAsItWas(callable $newEntity): void
    {
        $entity = $newEntity();
        $entity->setFormation($this->cuisine);

        $form = $this->createForm($entity);
        $form->submit(['formation' => 'not-a-choice']);

        $this->assertFalse($entity->isNewBeneficiaryOnly());
        $this->assertSame($this->cuisine, $entity->getFormation());
    }

    public function entities(): array
    {
        return [
            'shift' => [function () {
                return new Shift();
            }],
            'period position' => [function () {
                return new PeriodPosition();
            }],
        ];
    }
}
