<?php

namespace Specification\Akeneo\Pim\Structure\Bundle\Form\Type;

use PhpSpec\ObjectBehavior;
use Akeneo\Pim\Structure\Component\Model\AssociationType;
use Akeneo\Pim\Structure\Component\Model\AssociationTypeTranslation;
use Akeneo\Platform\Bundle\UIBundle\Form\Subscriber\DisableFieldSubscriber;
use Akeneo\Platform\Bundle\UIBundle\Form\Type\TranslatableFieldType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AssociationTypeTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(AssociationType::class);
    }

    function it_is_a_form_type()
    {
        $this->shouldBeAnInstanceOf(AbstractType::class);
    }

    function it_has_a_block_prefix()
    {
        $this->getBlockPrefix()->shouldReturn('pim_enrich_associationtype');
    }

    function it_builds_form(FormBuilderInterface $builder)
    {
        $builder->add('code')->shouldBeCalled();
        $builder->addEventSubscriber(new DisableFieldSubscriber('code'))->shouldBeCalled();

        $builder->add(
            'label',
            TranslatableFieldType::class,
            [
                'field'             => 'label',
                'translation_class' => AssociationTypeTranslation::class,
                'entity_class'      => AssociationType::class,
                'property_path'     => 'translations'
            ]
        )->shouldBeCalled();

        $this->buildForm($builder, []);
    }

    function it_sets_default_options(OptionsResolver $resolver)
    {
        $this->configureOptions($resolver);

        $resolver->setDefaults(
            [
                'data_class' => AssociationType::class,
            ]
        )->shouldHaveBeenCalled();
    }
}

