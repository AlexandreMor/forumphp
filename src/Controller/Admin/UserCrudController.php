<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Security;

class UserCrudController extends AbstractCrudController
{
    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    /**
     * @var Security
     */
    private $security;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder, EntityManagerInterface $entityManager, Security $security)
    {
        $this->passwordEncoder = $passwordEncoder;
        $this->entityManager = $entityManager;
        $this->security = $security;

        // get the user id from the logged in user
        if (null !== $this->security->getUser()) {
            $this->password = $this->security->getUser()->getPassword();
        }
    }
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $password = TextField::new('password')
            ->setFormType(PasswordType::class)
            ->setFormTypeOption('empty_data', '')
            ->setRequired(false)
            ->setHelp('If the right is not given, leave the field blank.');
        return [
            TextField::new('username'),
            TextField::new('email'),
            $password,
            TextField::new('pic'),
            TextField::new('github'),
            TextField::new('signature'),
            TextEditorField::new('bio'),
            BooleanField::new('isBanned'),
            ChoiceField::new('roles', 'Roles')
                ->allowMultipleChoices()
                ->autocomplete()
                ->setChoices([
                    'Utilisateur' => 'ROLE_USER',
                    'Modérateur' => 'ROLE_MOD',
                    'Administrateur' => 'ROLE_ADMIN'
                ]),
        ];
    }
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {


        // set new password with encoder interface
        if (method_exists($entityInstance, 'setPassword')) {
            $clearPassword = trim($this->get('request_stack')->getCurrentRequest()->request->all('User')['password']);

            // if user password not change save the old one
            if (isset($clearPassword) === true && $clearPassword === '') {
                $entityInstance->setPassword($this->password);
            } else {
                $encodedPassword = $this->passwordEncoder->encodePassword($this->getUser(), $clearPassword);
                $entityInstance->setPassword($encodedPassword);
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
