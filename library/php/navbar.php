<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sidebar Menu HTML and CSS | CodingNepal</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
  <link rel="stylesheet" href="../css/navbar.css">
</head>

<body>
  <aside class="sidebar">
    <div class="sidebar-header">

    <br>
    
    </div>
    <ul class="sidebar-links">
      <br>
      <h4>
        <span>Main Menu</span>
        <div class="menu-separator"></div>
      </h4>
      <li>
        <a href="home.php">
          <span class="material-symbols-outlined"> dashboard </span>Dashboard
        </a>
      </li>
      <li>
        <a href="inventory.php">
          <span class="material-symbols-outlined"> inventory </span>Book Inventory
        </a>
      </li>
      <li>
        <a href="addition.php">
          <span class="material-symbols-outlined"> add </span>Add Book/s
        </a>
      </li>
      <li class="dropdown">
        <div class="dropdown-dropdown"></div>
        <a href="#" class="dropdown-toggle">
          <span class="material-symbols-outlined"> box </span>
          <p class="borrow-word">Borrow</p>
          <span class="material-symbols-outlined">arrow_drop_down </span>
        </a>
        <div class="dropdown-container">
          <ul class="dropdown-menu">
            <li><a href="addborrow.php">
                <span class="material-symbols-outlined"> box </span>Borrow a Book
              </a></li>
            <li><a href="borrowlist.php">
                <span class="material-symbols-outlined"> box </span>Borrowed List
              </a></li>
            <li><a href="borrowhistory.php">
                <span class="material-symbols-outlined"> box </span>Borrow History
              </a></li>
          </ul>
        </div>
      </li>

      <div class="account">
        <h4>
          <div class="menu-separator"></div>
        </h4>
        <h4>
          <span>Account</span>
        </h4>
        <li>
          <a href="settings.php"><span class="material-symbols-outlined"> settings </span>Settings</a>
        </li>
        <li>
          <a href="logout.php"><span class="material-symbols-outlined"> logout </span>Logout</a>
        </li>
      </div>
    </ul>
  </aside>
  <script>
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        this.parentElement.classList.toggle('dropdown-open');
      });
    });
  </script>

</body>

</html>