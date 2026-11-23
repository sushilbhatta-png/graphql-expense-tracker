const form = document.getElementById("expenseForm");
const result = document.getElementById("result");
const expenseList = document.getElementById("expenseList");

// 2. Define fetchExpenses()
async function fetchExpenses(category) {
  const requestQueryData = {
    query: `
                query{
                    expenses{ 
                        id
                        title
                        amount
                        category
                    }
                }
            `,
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
    console.log(resData);

    if (resData.errors) {
        throw new Error(resData.errors[0].message);
    }

    const allExpenses = resData.data.expenses;

    // Clear Previous List
    expenseList.innerHTML = "";
    
    // Create one Ul
    const ul = document.createElement("ul");

    // Loop through expenses
    allExpenses.forEach((expense) => {

         // Create an individual list item
        const li = document.createElement("li");
        li.innerHTML = `
                        <strong>ID : ${expense.id}</strong>                         <strong>Title : ${expense.title}</strong>
                        <strong>Amount : ${expense.amount}</strong>
                        <strong>Category : ${expense.category}</strong>
                        
                    `;
      ul.appendChild(li);
    });

    // add ul to the page
    expenseList.appendChild(ul)

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
 fetchExpenses();
