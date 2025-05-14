<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('Patient');

$patient_id = $_SESSION['patient_id'];
$patient_user_id = $_SESSION['user_id'];

// Get assigned doctor's User_ID
$stmt = $conn->prepare("SELECT d.User_ID, u.Full_Name 
                       FROM DoctorPatientMapping dm 
                       JOIN Doctor d ON dm.Doctor_ID = d.Doctor_ID 
                       JOIN User u ON d.User_ID = u.User_ID 
                       WHERE dm.Patient_ID = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$doctor_user_id = $doctor['User_ID'] ?? null;
$doctor_name = $doctor['Full_Name'] ?? 'Your Doctor';

if (!$doctor_user_id) {
    setAlert('No doctor assigned to you yet', 'error');
    header("Location: dashboard.php");
    exit();
}

// Fetch chat history and mark messages as read
$messages = [];
if ($doctor_user_id) {
    // Fetch chat history
    $stmt = $conn->prepare("SELECT n.*, u.Full_Name AS Sender_Name 
                           FROM Notifications n 
                           JOIN User u ON n.Sender_ID = u.User_ID 
                           WHERE (n.User_ID = ? AND n.Sender_ID = ?) OR (n.User_ID = ? AND n.Sender_ID = ?) 
                           ORDER BY n.Sent_At ASC");
    $stmt->bind_param("iiii", $doctor_user_id, $patient_user_id, $patient_user_id, $doctor_user_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Mark all unread messages from the doctor as read
    $stmt = $conn->prepare("UPDATE Notifications 
                           SET Status = 'Read' 
                           WHERE User_ID = ? AND Sender_ID = ? AND Status = 'Sent' 
                           AND Alert_Type IN ('Message', 'Reply')");
    $stmt->bind_param("ii", $patient_user_id, $doctor_user_id);
    $stmt->execute();
}

// Handle sending a reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = sanitize($conn, $_POST['message']);
    $stmt = $conn->prepare("INSERT INTO Notifications (User_ID, Sender_ID, Alert_Type, Message, Sent_At, Status) 
                           VALUES (?, ?, 'Reply', ?, NOW(), 'Sent')");
    $stmt->bind_param("iis", $doctor_user_id, $patient_user_id, $message);
    if ($stmt->execute()) {
        setAlert('Message sent successfully', 'success');
        header("Location: chat.php");
        exit();
    }
}
?>

<div class="patient-chat">
    <h2>Chat with Dr. <?= htmlspecialchars($doctor_name) ?></h2>
    <div class="back-link">
        <a href="dashboard.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="chat-container card">
        <div class="chat-messages" id="chatMessages">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?= $msg['Sender_ID'] == $patient_user_id ? 'sent' : 'received' ?>">
                        <p><strong><?= htmlspecialchars($msg['Sender_Name']) ?>:</strong> <?= htmlspecialchars($msg['Message']) ?></p>
                        <span class="timestamp"><?= date('M j, g:i A', strtotime($msg['Sent_At'])) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No messages yet. Start the conversation!</p>
            <?php endif; ?>
        </div>
        <form method="post" class="chat-form">
            <input type="text" name="message" placeholder="Type your message..." required>
            <button type="submit" class="btn">Send</button>
        </form>
    </div>
</div>

<script>
    // Function to scroll to the bottom of chat messages
    function scrollToBottom() {
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Scroll to bottom when page loads
    document.addEventListener('DOMContentLoaded', scrollToBottom);

    // Scroll to bottom when new messages are added
    const chatForm = document.querySelector('.chat-form');
    if (chatForm) {
        chatForm.addEventListener('submit', () => {
            // Use setTimeout to ensure scroll happens after message is added
            setTimeout(scrollToBottom, 100);
        });
    }
</script>

<style>
/* Patient Chat Styles matching Dashboard */
.patient-chat {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.patient-chat h2 {
    color: var(--primary-dark);
    font-size: 2.2rem;
    margin-bottom: 1.5rem;
    font-weight: 700;
    text-align: center;
    position: relative;
    padding-bottom: 1rem;
}

.patient-chat h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: var(--secondary);
    border-radius: 2px;
}

.back-link {
    margin-bottom: 1.5rem;
    text-align: left;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.5rem;
    background-color: var(--primary-dark);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-back:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(96, 108, 56, 0.3);
}

.btn-back i {
    margin-right: 0.5rem;
}

.chat-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.chat-container:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.chat-messages {
    max-height: 500px;
    overflow-y: auto;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid rgba(0, 0, 0, 0.05);
    scroll-behavior: smooth; /* Add smooth scrolling */
}

.message {
    margin: 1rem 0;
    padding: 1rem;
    border-radius: 8px;
    position: relative;
    max-width: 80%;
    word-wrap: break-word;
}

.message.sent {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    margin-left: auto;
    text-align: left;
}

.message.received {
    background: white;
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin-right: auto;
    text-align: left;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
}

.message p {
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
    line-height: 1.5;
}

.message.sent p {
    color: white;
}

.message.received p {
    color: var(--dark-gray);
}

.message strong {
    font-weight: 600;
    color: inherit;
}

.timestamp {
    display: block;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.8);
    font-style: italic;
}

.message.received .timestamp {
    color: var(--secondary-dark);
}

.chat-form {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.chat-form input {
    flex-grow: 1;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.chat-form input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(96, 108, 56, 0.1);
}

.chat-form .btn {
    padding: 0.75rem 1.5rem;
    background-color: var(--primary-dark);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.chat-form .btn:hover {
    background-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(96, 108, 56, 0.3);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .patient-chat {
        padding: 1.5rem;
    }
    
    .chat-container {
        padding: 1.5rem;
    }
    
    .message {
        max-width: 90%;
    }
    
    .chat-form {
        flex-direction: column;
    }
    
    .chat-form input,
    .chat-form .btn {
        width: 100%;
    }
}

@media (max-width: 576px) {
    .patient-chat {
        padding: 1rem;
    }
    
    .patient-chat h2 {
        font-size: 1.8rem;
    }
    
    .chat-messages {
        max-height: 400px;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>
