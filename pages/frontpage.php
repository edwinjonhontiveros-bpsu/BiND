<?php
require_once '../login-sec/connection.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['Fname']) || !isset($_SESSION['Avatar'])) {
    header("Location: ../login-sec/login.php");
    exit();
}

// Retrieve user information for the header
$Fname_header = $_SESSION['Fname'];
$Lname_header = $_SESSION['Lname'];
$Avatar_header = $_SESSION['Avatar'];

$Date = date('Y-m-d'); // Get current Date

// Retrieve user information for the posts
$Fname_post = $_SESSION['Fname'];
$Lname_post = $_SESSION['Lname'];
$admin_Avatar = $_SESSION['Avatar'];

$AdminID = $_SESSION['AdminID'];

if (isset($_SESSION['UserID'])) {
    $UserID = $_SESSION['UserID'];
} else {
    $UserID = '';
}

// Handle like post action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_post'])) {
    $PostID = $_POST['PostID'];

    $conn = getDBConnection();
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }

    // Check if the user has already liked the post
    $check_like = $conn->prepare("SELECT * FROM likes WHERE UserID = ? AND PostID = ?");
    $check_like->bind_param("ii", $UserID, $PostID);
    $check_like->execute();
    $result_check_like = $check_like->get_result();

    if ($result_check_like->num_rows > 0) {
        // User has already liked the post, so unlike it
        $unlike_post = $conn->prepare("DELETE FROM likes WHERE UserID = ? AND PostID = ?");
        $unlike_post->bind_param("ii", $UserID, $PostID);
        $unlike_post->execute();
        $unlike_post->close();
    } else {
        // User has not liked the post yet, so like it
        $like_post = $conn->prepare("INSERT INTO likes (UserID, PostID) VALUES (?, ?)");
        $like_post->bind_param("ii", $UserID, $PostID);
        $like_post->execute();
        $like_post->close();
    }

    $check_like->close();
    $conn->close();

    // Redirect to the same page to avoid form resubmission
    header("Location: #like");
    exit();
}

// Fetch categories
$conn = getDBConnection();
$sql = "SELECT CategoryID, Category FROM categories";
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Home</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
   <link rel="stylesheet" type="text/css" href="../css/frontpage.css">
</head>
<body>
    <?php require_once "../components/user_header.php"; ?>

    <section class="home-grid">
        <div class="box-container">
            <div class="box">
                <?php
                $conn = getDBConnection();
                $select_profile = $conn->prepare("SELECT * FROM `admins` WHERE AdminID = ?");
                $select_profile->bind_param("i", $AdminID);
                $select_profile->execute();
                $result_profile = $select_profile->get_result();

                $count_user_comments = $conn->prepare("SELECT * FROM `comments` WHERE UserID = ?");
                $count_user_comments->bind_param("i", $UserID);
                $count_user_comments->execute();
                $result_comments = $count_user_comments->get_result();
                $total_user_comments = $result_comments->num_rows;

                $count_user_likes = $conn->prepare("SELECT * FROM `likes` WHERE UserID = ?");
                $count_user_likes->bind_param("i", $UserID);
                $count_user_likes->execute();
                $result_likes = $count_user_likes->get_result();
                $total_user_likes = $result_likes->num_rows;
                ?>
                <p> Welcome <span><?= htmlspecialchars($Fname_header . ' ' . $Lname_header); ?></span></p>
                <p>total comments : <span><?= htmlspecialchars($total_user_comments); ?></span></p>
                <p>posts liked : <span><?= htmlspecialchars($total_user_likes); ?></span></p>
                <a href="update.php" class="btn">update profile</a>
            </div>

            <div class="box">
                <p>categories</p>
                <div class="flex-box">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo '<a href="category.php?Category=' . urlencode($row["Category"]) . '" class="links">' . htmlspecialchars($row["Category"]) . '</a>';
                        }
                    } else {
                        echo "0 results";
                    }
                    ?>
                    <a href="all_category.php" class="btn">view all</a>
                </div>
            </div>

            <div class="box">
                <p>Authors</p>
                <div class="flex-box">
                    <?php
                    $select_authors = $conn->prepare("SELECT DISTINCT admins.AdminID, CONCAT(admins.Fname, ' ', admins.Lname) AS name FROM admins 
                                                      JOIN posts ON admins.AdminID = posts.AdminID LIMIT 5");
                    $select_authors->execute();
                    $result_authors = $select_authors->get_result();

                    if ($result_authors->num_rows > 0) {
                        while ($fetch_authors = $result_authors->fetch_assoc()) {
                    ?>
                    <a href="admin_posts.php?AdminID=<?= htmlspecialchars($fetch_authors['AdminID']); ?>" class="links"><?= htmlspecialchars($fetch_authors['name']); ?></a>
                    <?php
                        }
                    } else {
                        echo '<p class="empty">no authors found!</p>';
                    }
                    ?>
                    <a href="authors.php" class="btn">view all</a>
                </div>
            </div>
        </div>
    </section>

    <section class="posts-container">
        <h1 class="heading">Latest Posts</h1>
        <div class="box-container">
            <?php
            $conn = getDBConnection();
            if (!$conn) {
                die("Connection failed: " . mysqli_connect_error());
            }

            $select_posts = $conn->prepare("SELECT posts.*, admins.Fname, admins.Lname, admins.Avatar AS admin_Avatar, posts.MediaType, posts.MediaURL 
                                            FROM posts 
                                            JOIN admins ON posts.AdminID = admins.AdminID 
                                            WHERE posts.Status = ? LIMIT 6");
            $Status = 'Active';
            $select_posts->bind_param("s", $Status);
            $select_posts->execute();
            $result_posts = $select_posts->get_result();

            if ($result_posts->num_rows > 0) {
                while ($fetch_posts = $result_posts->fetch_assoc()) {
                    $PostID = $fetch_posts['PostID'];

                    // Retrieve the count of comments for the post
                    $count_post_comments = $conn->prepare("SELECT COUNT(*) AS total_comments FROM comments WHERE PostID = ?");
                    $count_post_comments->bind_param("i", $PostID);
                    $count_post_comments->execute();
                    $result_post_comments = $count_post_comments->get_result();
                    $total_post_comments = $result_post_comments->fetch_assoc()['total_comments'];

                    // Retrieve the count of likes for the post
                    $count_post_likes = $conn->prepare("SELECT COUNT(*) AS total_likes FROM likes WHERE PostID = ?");
                    $count_post_likes->bind_param("i", $PostID);
                    $count_post_likes->execute();
                    $result_post_likes = $count_post_likes->get_result();
                    $total_post_likes = $result_post_likes->fetch_assoc()['total_likes'];

                    // Check if the current user has liked the post
                    $confirm_likes = $conn->prepare("SELECT COUNT(*) AS liked FROM likes WHERE UserID = ? AND PostID = ?");
                    $confirm_likes->bind_param("ii", $UserID, $PostID);
                    $confirm_likes->execute();
                    $result_confirm_likes = $confirm_likes->get_result();
                    $is_liked = $result_confirm_likes->fetch_assoc()['liked'] > 0;
            ?>
                    <form class="box" method="post">
                        <input type="hidden" name="PostID" value="<?= htmlspecialchars($PostID); ?>">
                        <input type="hidden" name="AdminID" value="<?= htmlspecialchars($fetch_posts['AdminID']); ?>">
                        <div class="post-admin">
                            <div class="profile-img">
                                <img src="../upload/<?= htmlspecialchars($fetch_posts['admin_Avatar']); ?>" alt="Profile">
                            </div>
                            <div>
                                <a href="admin_posts.php?AdminID=<?= urlencode($fetch_posts['AdminID']); ?>"><?= htmlspecialchars($fetch_posts['Fname'] . ' ' . $fetch_posts['Lname']); ?></a>
                                <div><?= htmlspecialchars($fetch_posts['Date']); ?></div>
                            </div>
                        </div>
                        <?php
                        if (!empty($fetch_posts['MediaURL'])) {
                            if ($fetch_posts['MediaType'] == 'image') {
                        ?>
                        <img src="../uploaded_media/<?= htmlspecialchars($fetch_posts['MediaURL']); ?>" class="post-image" alt="">
                        <?php
                            } elseif ($fetch_posts['MediaType'] == 'video') {
                        ?>
                        <video controls class="post-image">
                            <source src="../uploaded_media/<?= htmlspecialchars($fetch_posts['MediaURL']); ?>" type="video/mp4">
                            <source src="../uploaded_media/<?= htmlspecialchars($fetch_posts['MediaURL']); ?>" type="video/webm">
                            <source src="../uploaded_media/<?= htmlspecialchars($fetch_posts['MediaURL']); ?>" type="video/ogg">
                            <source src="../uploaded_media/<?= htmlspecialchars($fetch_posts['MediaURL']); ?>" type="video/quicktime">
                            <source src="../uploaded_media/<?= htmlspecialchars($fetch_posts['MediaURL']); ?>" type="video/mov">
                            Your browser does not support the video tag.
                        </video>
                        <?php
                            }
                        }
                        ?>
                        <div class="post-title"><?= htmlspecialchars($fetch_posts['Title']); ?></div>
                        <div class="post-content content-150"><?= htmlspecialchars($fetch_posts['Content']); ?></div>
                        <a href="view_post.php?PostID=<?= htmlspecialchars($PostID); ?>" class="inline-btn">read more</a>
                        <a href="category.php?Category=<?= htmlspecialchars($fetch_posts['Category']); ?>" class="post-cat"><i class="fas fa-tag"></i> <span><?= htmlspecialchars($fetch_posts['Category']); ?></span></a>
                        <div class="icons">
                            <a href="view_post.php?PostID=<?= htmlspecialchars($PostID); ?>"><i class="fas fa-comment"></i><span>(<?= htmlspecialchars($total_post_comments); ?>)</span></a>
                            <button id="like" name="like_post" class="like"><i class="fas fa-heart" style="<?= $is_liked ? 'color: red;' : ''; ?>"></i><span>(<?= htmlspecialchars($total_post_likes); ?>)</span></button>
                        </div>
                    </form>
            <?php
                }
            } else {
                echo '<p class="empty">No posts added yet!</p>';
            }

            // Close the prepared statements
            $select_posts->close();
            if (isset($count_post_comments)) $count_post_comments->close();
            if (isset($count_post_likes)) $count_post_likes->close();
            if (isset($confirm_likes)) $confirm_likes->close();

            // Close the database connection
            $conn->close();
            ?>
        </div>
        <div class="more-btn" style="text-align: center; margin-top:1rem;">
            <a href="posts.php" class="inline-btn">view all posts</a>
        </div>
    </section>

<!-- custom js file link  -->
<script src="../js/script.js"></script>

</body>
</html>
