<?php

namespace App\Tests\Form;

use App\Form\SignupType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class SignupTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidData(): void
    {
        $form = $this->factory->create(SignupType::class);

        $formData = [
            'email' => 'john.doe+' . uniqid('', true) . '@example.test',
            'password' => 'secret123',
            'nom' => 'Doe',
            'prenom' => 'John',
            'telephone' => '0612345678',
            'ville' => 'Tunis',
            'dateNaissance' => null,
            'role' => 'patient',
            'photoProfil' => null,
        ];

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
    }

    public function testPasswordTooShort(): void
    {
        $form = $this->factory->create(SignupType::class);

        $formData = [
            'email' => 'short.pass@example.test',
            'password' => '123',
            'nom' => 'Doe',
            'prenom' => 'John',
            'telephone' => '0612345678',
            'ville' => 'Tunis',
            'dateNaissance' => null,
            'role' => 'patient',
            'photoProfil' => null,
        ];

        $form->submit($formData);

        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get('password')->getErrors(true)->count());
    }

    public function testPasswordRequired(): void
    {
        $form = $this->factory->create(SignupType::class);

        $formData = [
            'email' => 'no.pass@example.test',
            'password' => '',
            'nom' => 'Doe',
            'prenom' => 'John',
            'telephone' => '0612345678',
            'ville' => 'Tunis',
            'dateNaissance' => null,
            'role' => 'patient',
            'photoProfil' => null,
        ];

        $form->submit($formData);

        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get('password')->getErrors(true)->count());
    }
}
