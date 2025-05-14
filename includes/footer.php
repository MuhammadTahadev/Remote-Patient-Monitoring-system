<?php
// includes/footer.php
?>
    </main>
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Remote Patient Monitoring System</p>
            <nav>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Contact Us</a>
            </nav>
        </div>
    </footer>
    <script src="../assets/js/main.js"></script>
</body>
</html>
<?php
// End output buffering and flush
ob_end_flush();
?>