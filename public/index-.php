<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//__DIR__ return the current directory of  PHP file.(/public)
require_once __DIR__ . '/../vendor/autoload.php';  
require_once __DIR__ . '/../config/database.php';
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\GraphQL;

//autoloader lets pgp load classes from installed composer packages without manually including each class file.

// Sample Expenses Data 
$sampleExpenses = [
    [
        'id' => 1,
        'title' => 'Lunch',
        'amount' => 15.50,
        'category' => 'Food',
    ],
    [
        'id' => 2,
        'title' => 'Bus fare',
        'amount' => 3.50,
        'category' => 'Transport',
    ],
    [
        'id' => 3,
        'title' => 'Groceries',
        'amount' => 45.75,
        'category' => 'Food',
    ],
];

// ObjectType defines the structure of an object (eg. an Expense);
$expenseType = new ObjectType([
        'name' => 'Expense',
        // fields Define the property avaliable on that Object
        'fields' => [
            'id' => Type::int(),
            'title' => Type::string(),
            'amount' => Type::float(),
            'category'=>Type::string(),
        ]
    ]
);


$categoryType = new EnumType([
    'name' => 'ExpenseCategory',
    'values'=>[
        'FOOD'=>'food',
        'TRANSPORT' => 'Transport',
        'SHOPPING' => 'Shopping',
        'OTHER' => 'Other'
    ],
]);

//
$queryType = new ObjectType([
    'name' => 'Query',
    'fields'=> [
        'expenses'=> [
            'type'=> Type::listOf($expenseType),
            'args' =>[
                //'category'=> Type::string(),
                // For Additional Configuration on category like defaultValue use assoc. array
                // 'category'=> [
                //     'type' => Type::string(),
                //     'defaultValue' => 'Food',
                // ],
                'category'=>[
                    'type'=>$categoryType,
                    // 'defaultValue' => 'food',  //Always use the food category 
                ],
            ],

            'resolve' => function($rootValue, array $args) use ($pdo){
                if(array_key_exists('category',$args)){
                    // Compare the expense category with the category received from $args(ie as per client's request)
                    
                    // Prepare a SQL Statement  with placeholder
                    $stmt = $pdo-> prepare(' select * from expenses where category= :category');
                    
                    // Execute the statement and pass the actual category passed from client 
                    $stmt->execute([
                        'category' => $args['category']
                    ]);

                    $filtered_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $result = array_filter($filtered_expenses,function ($expense) use($args) {
                       $required_value = $args['category'];
                       return strcasecmp($required_value,$expense['category'])===0;   
                    });

                    $result = array_values($result);
                    return $result;

                }else{
                    // Query all Expenses from the database.
                    $stmt = $pdo -> query('select * from expenses');
                    $stmt-> execute();
                    $all_expenses = $stmt -> fetchAll(PDO::FETCH_ASSOC);
                    return array_values($all_expenses);


                }
            }
        ],
    ],
]);

// InputType Defines the structured input (eg create a new expense by client);
$expenseInputType = new InputObjectType([
    'name'=> 'ExpenseInput',
    'fields' => [
        'title' => Type::string(),
        'amount' => Type::float(),
        'category'=> $categoryType,
    ],
]);

$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' =>[
        'createExpense'=>[
             'type' => $expenseType,
             'args'=> [
                'input' => Type::nonNull($expenseInputType),
             ],
             'resolve' => function($rootValue,array $args) use(&$sampleExpenses,$pdo){
                // Create a new Expense
                // Retrive inner array
                $input = $args['input'];    

                // Retrieve input
                $title = $input['title'];
                $amount = $input['amount'];
                $category = $input['category'];

                // SQL query
                $update_query = 'INSERT INTO expenses (title, amount, category)
                        VALUES (:title, :amount, :category)';

                // Prepare the SQL statement
                $stmt = $pdo->prepare($update_query);

                // Execute the statement
                $stmt->execute([
                    'title' => $title,
                    'amount' => $amount,
                    'category' => $category,
                ]);

                // Retrieve the automatically generated ID
                $newId = $pdo->lastInsertId();

                // Return the newly created expense
                return [
                    'id' => (int) $newId,
                    'title' => $title,
                    'amount' => $amount,
                    'category' => $category,
                ];


                // Create a new id 
                // $newId = count($sampleExpenses) + 1;

                // $newExpense = array(
                //     'id' => $newId,
                //     'title'=> $title,
                //     'amount' => $amount,
                //     'category'=> $category,
                // );
                // $sampleExpenses[] = $newExpense;
                // Return the newly created expense to GraphQL
                // return $insert_stmt;
             },
        ],
        'updateExpense'=>[
            'type' => $expenseType,
            'args' =>[
                'id' => Type::nonNull(Type::int()),
                'input'=> Type::nonNull($expenseInputType),
            ],
            'resolve'=>function($rootValue,array $args)use($pdo){

                // Update Logic

                // Retrive Inner Array
                $input = $args['input'];
                $title = $input['title'];
                $amount = $input['amount'];
                $category = $input['category'];

                // Retrive Id

                $id = $args['id'];
                $stmt = $pdo->prepare('update expenses set title= :title,amount= :amount,category= :category where id= :id');
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
            
            }
        ]
    ],
]);



$schema = new Schema([ //Create a graphql schema Object
// $queryType is the ObjectType Assigned to that role
    'query'=> $queryType, //'query' identifies the root type for read operations
    'mutation' => $mutationType, // mutation is the entry point for Write operation
]);

$query= '
    query {
        expenses(category:FOOD) {
        id    
        title
        amount
        category
        }
    }
';
//For future Ref
$mutation_without_variable = '
    mutation {
        createExpense(input: {
            title: "Coffee",
            amount: 4.50,
            category: FOOD
        }) {
            id
            title
            amount
            category
        }
    }
';


// Using the Variable

// `input` is the argument name. It must match the argument
// name defined in the PHP schema:
// 'input' => Type::nonNull($expenseInputType)

// `$input` is the GraphQL variable that will receive the
// value supplied separately when executing the mutation.
// $mutation = '
//     mutation CreateExpense($input: ExpenseInput!) {
//         createExpense(input: $input) {
//             id
//             title
//             amount
//             category
//         }
//     }
// ';

$mutation = '
    mutation UpdateExpense($id:Int!,$input: ExpenseInput! ){
        updateExpense(id:$id,input,$input){
            id
            title
            amount
            category
        }
    }
';

// Build the update test data

$update_data = [
    'id' => '2',
    'input'=>[
        'title'=>'Dinner',
        'amount'=> 243.4,
        'category' => 'Food'
    ],
];

$update_result = GraphQL::executeQuery(
    $schema,
    $mutation,
    null,
    null,
    $update_data
    );

// $variableValues = [
//     'input' =>[
//     'title' => 'Coffee',
//     'amount' => 4.50,
//     'category' => 'FOOD'
//     ],
// ];

// Signature
// GraphQL::executeQuery($schema, $source, $rootValue, $variableValues);

// $result = GraphQL::executeQuery(
//     $schema,
//     $mutation,
//     null,
//     null,
//     $variableValues
// );
// $request = json_decode(file_get_contents('php://input'), true);

// var_dump($request);

// Actual client Server Implementation

// json_decode converts it into an associative array
$request = json_decode(file_get_contents('php://input'),true);
$mutation = $request['query'];
$variableValues = $request['variables']??[];

$result = GraphQL::executeQuery(
    $schema,
    $mutation,
    null,
    null,
    $variableValues
);
// converts the PHP array back into JSON.
$output = $result->toArray();
// tells the client the response is JSON.
header('Content-Type: application/json'); //tells client php return the json data
// echo sends the JSON response to the client.
echo json_encode($output);




/*
Schema acts as the entry point to your API. it tells graphql what the client is allowed to ask for. 


Graphql needs a starting point for that request. That starting point is called the Root Query( or Query Type).

Query is a special object type that defines the available read operation.
eg we want our API to allow a client to fetch a single expense.

{
    expense
    {
        id
        title
        amount
    }
}
This is how the client Use
mutation {
    createExpense(input: {
        title: "Lunch",
        amount: 15.50,
        category: FOOD
    }) {
        id
        title
        category
    }
}
*/

