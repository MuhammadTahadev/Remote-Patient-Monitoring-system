const links = document.querySelectorAll(".navbar a");

links.forEach(link => {
    if (link.href.includes("login.php") || link.href.includes("register.php")) return;
    link.addEventListener("click", () => {
        links.forEach(active_remove => {
            if (active_remove.href.includes("login.php") || active_remove.href.includes("register.php")) return;

            active_remove.classList.remove("active");
        })

        link.classList.add("active");
    })
})