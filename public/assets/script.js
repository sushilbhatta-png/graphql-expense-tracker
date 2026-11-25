const form = document.getElementById("expenseForm");
const result = document.getElementById("result");
const expenseList = document.getElementById("expenseList");
// Update Form
const updateForm = document.getElementById('updateExpenseForm');
const updateResult = document.getElementById('updateResult');

const categoryFilter = document.getElementById('categoryFilter');


categoryFilter.addEventListener('change',(e)=>{
  const selectedOption = e.target.value
  console.log(selectedOption)
  if(selectedOption===''){
    fetchExpenses();
    return;
  } 

    fetchExpenses(selectedOption)
})

// 2. Define fetchExpenses()
async function fetchExpenses(category) {
  const requestQueryData = {
    query: `
                query FetchExpenses($category:ExpenseCategory){
                    expenses(category:$category){
                        id
                        title
                        amount
                        category
                    }
                }
            `,
  // GRAPHQL NOTES:
  // - Aliases allow us to request the same field multiple times in a single query using different names.
  // - Aliases change the response keys, not the actual schema field names.
  // - GraphQL variables ($category) allow us to pass dynamic values from JavaScript instead of hardcoding them.
  // - Aliases and variables are independent features and can be used together.
  // - Hardcoded enum values (e.g. TRANSPORT) are suitable for fixed queries, while variables are useful for dynamic filtering.

  // Example: Query using a variable to filter expenses by category.
  // Uncomment when needed.

  /*
  query FetchExpenses($category: ExpenseCategory) {
      expenses(category: $category) {
          id
          title
          amount
          category
      }
  }
  */
  //   'query __[alias example )': `
  //       query {
  // allExpenses: expenses {
  //   id
  //   title
  //   amount
  //   category
  // }

  // transportExpenses: expenses(category: TRANSPORT) {
  //   id
  //   title
  //   amount
  //   category
  // }

  // shoppingExpenses: expenses(category: SHOPPING) {
  //   id
  //   title
  //   amount
  //   category
  // }
// }
    // `,
    
    variables:{
      category
    }
  };
  try {
    const response = await fetch("http://localhost/graphql-demo/public/", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(requestQueryData),
    });

    //Error Handling
    if (!response.ok) {
      throw new Error(`HTTP Error ${response.status}`);
    }

    const resData = await response.json();

    if (resData.errors) {
        throw new Error(resData.errors[0].message);
    }

    const allExpenses = resData.data.expenses;
// Clear Previous List
    expenseList.innerHTML = "";

    // Create one Ul
    const ul = document.createElement("ul");
    ul.className = "expense-list";

    // Loop through expenses
    allExpenses.forEach((expense) => {
      const li = document.createElement("li");
      li.className = "expense-item";
      li.innerHTML = `
        <div class="expense-info">
          <span class="expense-title">${expense.title}</span>
          <span class="expense-meta">
            <span class="expense-category">${expense.category}</span>
            <span class="expense-id">#${expense.id}</span>
          </span>
        </div>
        <div class="expense-actions">
          <span class="expense-amount">${expense.amount}</span>
          <button type="button" class="expense-delete" data-id="${expense.id}">
            Delete
          </button>
        </div>
      `;
      ul.appendChild(li);
    });

    // add ul to the page
    expenseList.appendChild(ul);



const deleteButtons = expenseList.querySelectorAll('.expense-delete');

deleteButtons.forEach((button) => {
    button.addEventListener('click', () => {
        const expenseId = parseInt(button.dataset.id);
        deleteExpense(expenseId);
    });
});

  } catch (error) {
    console.error(error);
  }
}

// define create expense function
async function createExpense(title, amount, category) {
  // 1. Define Query data
  const requestData = {
    query: `
                mutation CreateExpense($input: ExpenseInput!) {
                    createExpense(input: $input) {
                        id
                        title
                        amount
                        category
                    }
                }
          `,
    variables: {
      input: {
        title: title,
        amount: amount,
        category: category,
      },
    },
  };
  try {
    const response = await fetch("http://localhost/graphql-demo/public/", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(requestData),
    });

    if (!response.ok) {
      throw new Error(`HTTP ERROR: ${response.status}`);
    }

    const data = await response.json();

    if (data.errors) {
    throw new Error(data.errors[0].message);
}

    const expense = data.data.createExpense;

    result.innerHTML = `
                <h3>Expense Added Successfully</h3>
                <p><strong>ID:</strong> ${expense.id}</p>
                <p><strong>Title:</strong> ${expense.title}</p>
                <p><strong>Amount:</strong> ${expense.amount}</p>
                <p><strong>Category:</strong> ${expense.category}</p>
            `;
  } catch (error) {
    result.textContent = `Could not add the expense: ${error.message}`;
    console.error(error);
  }
}


// Update Expenses

async function updateExpense(id,title,amount,category){
  // Request Data for Update
  const $requestUpdateData = {
    query: `
      mutation UpdateExpense($id:Int!,$input:ExpenseInput!){
        updateExpense(id:$id,input:$input){
          id
          title
          amount
          category
        }
      }
    `,
    variables:{
      id,
      input:{
        title,
        amount,
        category
      }
    }
  };

  // HTTP Request Handling
  try {
    const response = await fetch("http://localhost/graphql-demo/public/",{
      method: "POST",
      headers:{
        'Content-Type': 'application/json',
      },
      body:JSON.stringify($requestUpdateData)
    });

    // Handle Invalid Response
    if(!response.ok){
      throw new Error(`HTTP Error ${response.status}`);
      
    }
    const resData = await response.json();

    if(resData.errors){
      throw new Error(resData.errors[0].message)
    }
    const updatedExpense = resData.data.updateExpense;

    updateResult.innerHTML = `
      <strong>Expense ID ${updatedExpense.id} updated successfully.</strong>
      <p>Title: ${updatedExpense.title}</p>
      <p>Amount: ${updatedExpense.amount}</p>
      <p>Category: ${updatedExpense.category}</p>
    `;

    await fetchExpenses();

    console.log(updatedExpense);

  } catch (error) {
    updateResult.textContent = `Could not update expense: ${error.message}`;
    console.error(error);
  }
}


async function deleteExpense(expenseId) {
  const requestDeleteData = {
    'query':`
      mutation DeleteExpense($id:Int!){
        deleteExpense(id:$id){
          id
        }
      }
    `,
    'variables':{
      id:expenseId
    },
  }
  
  try {
    const response = await fetch('http://localhost/graphql-demo/public/',{
      method:"POST",
      headers:{
        'Content-Type' : 'application/json'
      },
      body:JSON.stringify(requestDeleteData)
    })
    if (!response.ok){
      throw new Error(`HTTP ${response.status} Error`);
      
    }
    const resData = await response.json();
    if (deleteExpense.errors) {
    throw new Error(deleteExpense.errors[0].message);
    }
   const deletedExpense = resData.data.deleteExpense;
   
    await fetchExpenses();
  } catch (error) {
    console.error('Could not delete expense:', error);

  }

}

form.addEventListener("submit", async (e) => {

    // Local Scoped 
  const title = document.getElementById("title").value;
  const amount = parseFloat(document.getElementById("amount").value);
  const category = document.getElementById("category").value;

  e.preventDefault();


  await createExpense(title, amount, category);
  await fetchExpenses();
});

// Fetch all expenses on page load.
 
updateForm.addEventListener("submit", async (e) => {

    // Local Scoped 
  const updateId = parseInt(document.getElementById('updateId').value);
  const title = document.getElementById("updateTitle").value;
  const amount = parseFloat(document.getElementById("updateAmount").value);
  const category = document.getElementById("updateCategory").value;


  e.preventDefault();


  await updateExpense(updateId,title,amount,category);
});
 fetchExpenses();
