
<?php

// ============================================
// 1. PHP CONFIGURATION & DEPENDENCIES
// ============================================

// Temporary debugging settings
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Database connection
require_once __DIR__ . '/../config/database.php';

// GraphQL imports
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Schema;
use GraphQL\GraphQL;


// ============================================
// 2. HISTORICAL REFERENCE: SAMPLE DATA
// ============================================

/*
HISTORICAL CODE:

We initially used a PHP array to store expenses instead of
a database. This helped us understand GraphQL resolvers,
queries, mutations and how data was returned.

We eventually replaced this approach with MySQL and PDO
so data would persist after requests.

KEEP YOUR ORIGINAL $sampleExpenses ARRAY HERE.
*/

// $sampleExpenses = [...your original array...];


// ============================================
// 3. GRAPHQL OUTPUT TYPE: EXPENSE
// ============================================


$userType =new ObjectType([
    'name'=> 'User',
    'fields' => [
        'id' => Type::int(),
        'name' => Type::string(),
    ],
]);


// Defines the structure of an Expense object returned
// by GraphQL to the client.

$expenseType = new ObjectType([
    'name' => 'Expense',
    'fields' => [
        'id' => Type::int(),
        'title' => Type::string(),
        'amount' => Type::float(),
        'category' => Type::string(),
        // 'user'=>[
        //         'type'=>$userType,
        //         'resolve'=>function($rootValue, array $args) use($pdo){
        //             $userId = $rootValue['user_id'];
                    
        //             $stmt = $pdo->prepare('select * from users where id= :id');

        //             $stmt->execute([
        //                 'id' => $userId
        //             ]);
        //             return $stmt->fetch(PDO::FETCH_ASSOC);
        //         }
        //     ]
        'user'=> [
            'type' => $userType
        ]
    ],
]);



// ============================================
// 4. GRAPHQL ENUM: EXPENSE CATEGORY
// ============================================

// GraphQL enum values map to PHP/database values.

$categoryType = new EnumType([
    'name' => 'ExpenseCategory',
    'values' => [
        'FOOD' => 'food',
        'TRANSPORT' => 'Transport',
        'SHOPPING' => 'Shopping',
        'OTHER' => 'Other',
    ],
]);


// ============================================
// 5. GRAPHQL QUERY: EXPENSES
// ============================================

// Query retrieves expenses from the database.
// Optional category argument allows filtering.

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'expenses' => [
            'type' => Type::listOf($expenseType),
            'args' => [
                'category' => $categoryType,
            ],
            'resolve' => function($rootValue, array $args) use ($pdo) {
                                    // Using Join to solve N+1 problem

            $sql = '
                SELECT
                    expenses.id,
                    expenses.title,
                    expenses.amount,
                    expenses.category,
                    users.id AS user_id,
                    users.name AS user_name
                FROM expenses
                LEFT JOIN users
                    ON expenses.user_id = users.id
            ';

                // Filter directly in SQL when category is supplied.
                // if (array_key_exists('category', $args)) {
                if (!empty($args['category'])){
                    $sql .= " WHERE expenses.category= :category";

                    // $stmt = $pdo->prepare(
                    //     'SELECT * FROM expenses WHERE category = :category'
                    // );

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        'category' => $args['category'],
                    ]);

                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                }else{
                    $stmt = $pdo -> query($sql);
                }

                // Otherwise retrieve all expenses.
                // $stmt = $pdo->query('SELECT * FROM expenses');

                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $expenses = [];
                foreach($rows as $row){
                    $expenses[]=
                            [
                                'id' =>      $row['id'],
                                'title' =>$row['title'],
                                'amount' => $row['amount'],
                                'category' => $row['category'],
                                'user' => 
                                [
                                    'id'=>$row['user_id'],
                                    'name' => $row['user_name'],
                                ]
                                
                            ];               
                }
                    return $expenses;
                // return $stmt->fetchAll(PDO::FETCH_ASSOC);
            },
        ],
    ],
]);


// ============================================
// 6. GRAPHQL INPUT TYPE: EXPENSE INPUT
// ============================================

// Used when the client sends expense data to GraphQL.

$expenseInputType = new InputObjectType([
    'name' => 'ExpenseInput',
    'fields' => [
        'title' => Type::string(),
        'amount' => Type::float(),
        'category' => $categoryType,
    ],
]);


// ============================================
// 7. GRAPHQL MUTATIONS
// ============================================

// Mutations modify data in the database.

$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => [

        // ----------------------------------------
        // CREATE EXPENSE
        // ----------------------------------------

        'createExpense' => [
            'type' => $expenseType,
            'args' => [
                'input' => Type::nonNull($expenseInputType),
            ],
            'resolve' => function($rootValue, array $args) use ($pdo) {

                $input = $args['input'];

                $title = $input['title'];
                $amount = $input['amount'];
                $category = $input['category'];

                $query = 'INSERT INTO expenses (title, amount, category)
                          VALUES (:title, :amount, :category)';

                $stmt = $pdo->prepare($query);

                $stmt->execute([
                    'title' => $title,
                    'amount' => $amount,
                    'category' => $category,
                ]);

                $newId = $pdo->lastInsertId();

                return [
                    'id' => (int) $newId,
                    'title' => $title,
                    'amount' => $amount,
                    'category' => $category,
                ];
            },
        ],

        // ----------------------------------------
        // UPDATE EXPENSE
        // ----------------------------------------

        'updateExpense' => [
            'type' => $expenseType,
            'args' => [
                'id' => Type::nonNull(Type::int()),
                'input' => Type::nonNull($expenseInputType),
            ],
            'resolve' => function($rootValue, array $args) use ($pdo) {

                $input = $args['input'];

                $title = $input['title'];
                $amount = $input['amount'];
                $category = $input['category'];
                $id = $args['id'];

                $stmt = $pdo->prepare(
                    'UPDATE expenses
                     SET title = :title,
                         amount = :amount,
                         category = :category
                     WHERE id = :id'
                );

               $stmt->execute([
                    'id'=>$id,
                    'title'=>$title,
                    'amount' => $amount,
                    'category' => $category,
                ]);

                if($stmt->rowCount()===0){
                    throw new Exception("Could not find id");   
                }
                    return [
                        'id'=>$id,
                        'title'=>$title,
                        'amount' => $amount,
                        'category' => $category,
                    ];      
            },
        ],

        // ----------------------------------------
        // UPDATE EXPENSE
        // ----------------------------------------
        'deleteExpense' => [
            'type'=>$expenseType,
            'args'=>[
                'id'=>Type::nonNull(Type::INT()),
            ],
            'resolve'=>function($rootValue,array $args) use($pdo){
                // Prepare SQL Query

                $id = $args['id'];
                
                $stmt = $pdo->prepare('delete from expenses where id= :id');
                
                // execute SQL Query
                $stmt->execute([
                    'id'=> $id
                ]);

                return [
                    'id'=> $id
                ];
                
            },
        ],
    ],
]);


// ============================================
// 8. GRAPHQL SCHEMA
// ============================================

// Schema connects the Query and Mutation root types.

$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType,
]);


// ============================================
// 9. HISTORICAL REFERENCE: TEST CASES
// ============================================

/*
KEEP YOUR PREVIOUS TEST CASES HERE.

Examples of historical tests:

- Initial GraphQL query using sample data.
- Query retrieving all expenses.
- Query filtering expenses by category.
- Initial mutation using in-memory array.
- Mutation using GraphQL variables.
- createExpense database insert test.
- updateExpense test attempts.
- Any errors encountered and their solutions.

Keep these commented out so they don't run as part
of the actual endpoint.

These are your learning notes and debugging history.
*/


// ============================================
// 10. HTTP REQUEST HANDLING
// ============================================

// Read the request body sent by the frontend.

$request = json_decode(file_get_contents('php://input'), true);

// Extract GraphQL query.
$mutation = $request['query'];

// Extract GraphQL variables, if supplied.
$variableValues = $request['variables'] ?? [];


// ============================================
// 11. EXECUTE GRAPHQL REQUEST & RESPONSE
// ============================================

$result = GraphQL::executeQuery(
    $schema,
    $mutation,
    null,
    null,
    $variableValues
);

// Convert the execution result to an array.
$output = $result->toArray();

// Set the response type.
header('Content-Type: application/json');

// Return JSON to the frontend.
echo json_encode($output);
