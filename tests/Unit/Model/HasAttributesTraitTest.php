<?php
namespace Mongolid\Model;

use MongoDB\BSON\ObjectId;
use Mongolid\TestCase;
use Mongolid\Tests\Stubs\PolymorphedReferencedUser;
use Mongolid\Tests\Stubs\ReferencedUser;

class HasAttributesTraitTest extends TestCase
{
    public function testShouldGetAttributeFromMutator()
    {
        // Set
        $model = new class()
        {
            use HasAttributesTrait;
            use HasRelationsTrait;

            public function __construct()
            {
                $this->mutable = true;
            }

            public function getShortNameDocumentAttribute()
            {
                return 'Other name';
            }
        };

        // Actions
        $model->setDocumentAttribute('short_name', 'My awesome name');
        $result = $model->getDocumentAttribute('short_name');

        // Assertions
        $this->assertSame('Other name', $result);
    }

    public function testShouldIgnoreMutators()
    {
        // Set
        $model = new class()
        {
            use HasAttributesTrait;
            use HasRelationsTrait;

            public function getShortNameDocumentAttribute()
            {
                return 'Other name';
            }

            public function setShortNameDocumentAttribute($value)
            {
                return strtoupper($value);
            }
        };

        // Actions
        $model->setDocumentAttribute('short_name', 'My awesome name');
        $result = $model->getDocumentAttribute('short_name');

        // Assertions
        $this->assertSame('My awesome name', $result);
    }

    public function testShouldSetAttributeFromMutator()
    {
        // Set
        $model = new class()
        {
            use HasAttributesTrait;
            use HasRelationsTrait;

            public function __construct()
            {
                $this->mutable = true;
            }

            public function setShortNameDocumentAttribute($value)
            {
                return strtoupper($value);
            }
        };

        // Actions
        $model->setDocumentAttribute('short_name', 'My awesome name');
        $result = $model->getDocumentAttribute('short_name');

        // Assertions
        $this->assertSame('MY AWESOME NAME', $result);
    }

    /**
     * @dataProvider getFillableOptions
     */
    public function testShouldFillOnlyPermittedAttributes(
        array $fillable,
        array $guarded,
        array $input,
        array $expected
    ) {
        // Set
        $model = new class($fillable, $guarded) implements HasAttributesInterface
        {
            use HasAttributesTrait;
            use HasRelationsTrait;

            public function __construct(array $fillable, array $guarded)
            {
                $this->fillable = $fillable;
                $this->guarded = $guarded;
            }
        };

        // Actions
        $model = $model::fill($input, $model);

        // Assertions
        $this->assertSame($expected, $model->getDocumentAttributes());
    }

    public function testFillShouldRetrievePolymorphedModel()
    {
        // Set
        $input = [
            'type' => 'polymorphed',
            'new_field' => 'hello',
        ];
        // Actions
        $result = ReferencedUser::fill($input);

        // Assertions
        $this->assertInstanceOf(PolymorphedReferencedUser::class, $result);
        $this->assertSame('polymorphed', $result->type);
        $this->assertSame('hello', $result->new_field);
    }

    public function testFillShouldRetrievePolymorphedModelConsideringModelAttributes()
    {
        // Set
        $input = [
            'new_field' => 'hello',
        ];
        $model = new ReferencedUser();
        $model->type = 'polymorphed';

        // Actions
        $result = ReferencedUser::fill($input, $model);

        // Assertions
        $this->assertInstanceOf(PolymorphedReferencedUser::class, $result);
        $this->assertSame('polymorphed', $result->type);
        $this->assertSame('hello', $result->new_field);
    }

    public function testFillShouldRetrievePolymorphedModelConsideringModelAttributesButPrioritizingInput()
    {
        // Set
        $input = [
            'type' => 'default',
            'new_field' => 'hello',
        ];
        $model = new PolymorphedReferencedUser();
        $model->type = 'polymorphed';

        // Actions
        $result = ReferencedUser::fill($input, $model);

        // Assertions
        $this->assertInstanceOf(ReferencedUser::class, $result);
        $this->assertSame('default', $result->type);
        $this->assertSame('hello', $result->new_field);
    }

    public function testFillShouldRetrievePolymorphedModelEvenWithExistingModel()
    {
        // Set
        $input = [
            'type' => 'polymorphed',
            'new_field' => 'hello',
            'exclusive' => 'value', // should not be set
            'other_exclusive' => 'value from fill', // should not be set
        ];
        $model = new ReferencedUser();
        $id = new ObjectId();
        $model->_id = $id;
        $model->name = 'Albert';
        $model->other_exclusive = 'other value'; // should be inherited
        // Actions
        $result = ReferencedUser::fill($input, $model);

        // Assertions
        $this->assertInstanceOf(PolymorphedReferencedUser::class, $result);
        $this->assertSame('polymorphed', $result->type);
        $this->assertSame('hello', $result->new_field);
        $this->assertSame($id, $result->_id);
        $this->assertSame('Albert', $result->name);
        $this->assertNull($result->exclusive);
        $this->assertSame('other value', $result->other_exclusive);
    }

    public function testFillShouldHoldValuesOnModel()
    {
        // Set
        $input = [
            'type' => 'regular',
            'new_field' => 'hello', // should not be set
        ];
        $model = new ReferencedUser();
        $id = new ObjectId();
        $model->_id = $id;
        $model->name = 'Albert';
        // Actions
        $result = ReferencedUser::fill($input, $model);

        // Assertions
        $this->assertSame($model, $result);
        $this->assertSame(
            [
                '_id' => $id,
                'name' => 'Albert',
                'type' => 'regular',
            ],
            $model->getDocumentAttributes()
        );
    }

    public function testFillShouldNotHoldValuesOnModelIfPolymorphed()
    {
        // Set
        $input = [
            'type' => 'polymorphed',
            'new_field' => 'hello',
        ];
        $model = new ReferencedUser();
        $id = new ObjectId();
        $model->_id = $id;
        $model->name = 'Albert';
        // Actions
        $result = ReferencedUser::fill($input, $model);

        // Assertions
        $this->assertNotSame($model, $result);
        $this->assertSame(
            [
                '_id' => $id,
                'name' => 'Albert',
                'type' => 'polymorphed',
                'new_field' => 'hello',
            ],
            $result->getDocumentAttributes()
        );
        $this->assertSame(
            [
                '_id' => $id,
                'name' => 'Albert',
            ],
            $model->getDocumentAttributes()
        );
    }

    public function testShouldForceFillAttributes()
    {
        // Set
        $model = new class() implements HasAttributesInterface
        {
            use HasAttributesTrait;
            use HasRelationsTrait;
        };

        $input = [
            'name' => 'Josh',
            'not_allowed_attribute' => true,
        ];

        // Actions
        $model = $model::fill($input, $model, true);
        $result = $model->getDocumentAttribute('not_allowed_attribute');

        // Assertions
        $this->assertTrue($result);
    }

    public function testShouldBeCastableToArray()
    {
        // Set
        $model = new class()
        {
            use HasAttributesTrait;
            use HasRelationsTrait;
        };

        $model->setDocumentAttribute('name', 'John');
        $model->setDocumentAttribute('age', 25);

        // Actions
        $result = $model->toArray();

        // Assertions
        $this->assertSame(['name' => 'John', 'age' => 25], $result);
    }

    public function testShouldSetOriginalAttributes()
    {
        // Set
        $model = new class() implements HasAttributesInterface
        {
            use HasAttributesTrait;
            use HasRelationsTrait;
        };

        $model->name = 'John';
        $model->age = 25;

        // Actions
        $model->syncOriginalDocumentAttributes();
        $result = $model->getOriginalDocumentAttributes();

        // Assertions
        $this->assertSame($model->getDocumentAttributes(), $result);
    }

    public function testShouldFallbackOriginalAttributesIfUnserializationFails()
    {
        // Set
        $model = new class() implements HasAttributesInterface
        {
            use HasAttributesTrait;
            use HasRelationsTrait;

            public function __construct()
            {
                $this->attributes = [
                    function () {
                    },
                ];
            }
        };

        // Actions
        $model->syncOriginalDocumentAttributes();
        $result = $model->getOriginalDocumentAttributes();

        // Assertions
        $this->assertSame($model->getDocumentAttributes(), $result);
    }

    public function testShouldCheckIfAttributeIsSet()
    {
        // Set
        $model = new class() extends AbstractModel
        {
        };

        // Actions
        $model = $model::fill(['name' => 'John', 'ignored' => null]);

        // Assertions
        $this->assertTrue(isset($model->name));
        $this->assertFalse(isset($model->nonexistant));
        $this->assertFalse(isset($model->ignored));
    }

    public function testShouldCheckIfMutatedAttributeIsSet()
    {
        // Set
        $model = new class() extends AbstractModel
        {
            /**
             * {@inheritdoc}
             */
            public $mutable = true;

            public function getNameDocumentAttribute()
            {
                return 'John';
            }
        };

        // Assertions
        $this->assertTrue(isset($model->name));
        $this->assertFalse(isset($model->nonexistant));
    }

    public function getFillableOptions(): array
    {
        return [
            '$fillable = []; $guarded = []' => [
                'fillable' => [],
                'guarded' => [],
                'input' => [
                    'name' => 'John',
                    'age' => 25,
                    'sex' => 'male',
                ],
                'expected' => [
                    'name' => 'John',
                    'age' => 25,
                    'sex' => 'male',
                ],
            ],
            '$fillable = ["name"]; $guarded = []' => [
                'fillable' => ['name'],
                'guarded' => [],
                'input' => [
                    'name' => 'John',
                    'age' => 25,
                    'sex' => 'male',
                ],
                'expected' => [
                    'name' => 'John',
                ],
            ],
            '$fillable = []; $guarded = [sex]' => [
                'fillable' => [],
                'guarded' => ['sex'],
                'input' => [
                    'name' => 'John',
                    'age' => 25,
                    'sex' => 'male',
                ],
                'expected' => [
                    'name' => 'John',
                    'age' => 25,
                ],
            ],
            '$fillable = ["name", "sex"]; $guarded = ["sex"]' => [
                'fillable' => ['name', 'sex'],
                'guarded' => ['sex'],
                'input' => [
                    'name' => 'John',
                    'age' => 25,
                    'sex' => 'male',
                ],
                'expected' => [
                    'name' => 'John',
                ],
            ],
            'ignore nulls but not falsy ones' => [
                'fillable' => ['name', 'surname', 'sex', 'age', 'has_sex'],
                'guarded' => [],
                'input' => [
                    'name' => 'John',
                    'surname' => '',
                    'sex' => null,
                    'age' => 0,
                    'has_sex' => false,
                ],
                'expected' => [
                    'name' => 'John',
                    'surname' => '',
                    'age' => 0,
                    'has_sex' => false,
                ],
            ],
        ];
    }
}
