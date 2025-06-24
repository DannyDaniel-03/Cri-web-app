// An immediatly invoked function, also known as an IIFE, its main purpose here is to grab notifications from the server and display them in 2 ways, an overlay pop-up modal, and dismissable
// alerts in a dedicated section (this part is kind of unused in a way), it checks for pop-up notifications so that it can provide almost real time updates.
(function () {
   //trns stuff into array elements by splitting them with the '/' which helps to give us the names of paths or files that are selected
  const page = location.pathname.split('/').pop();
  if(['login.php','register.php'].includes(page)) return;

    const listHost = document.getElementById('alertList');

    const shown = new Set();

  // it reaches an allowed page, and takes a raw response in which then it begins to parse it as a json which is then taken to the draw function
  fetch('api/user_notifications.php')
    .then(r => r.json())
    .then(draw);
        

  // after page loading, every 10 seconds it checks for new pop-ups
  function poll() {
        fetch('api/user_notifications.php?_=' + Date.now())
            .then(r => r.json())
            .then(d => d.popup.forEach(showModal));
  }
    setInterval(poll, 10000);

  // the main man, the RENDERER it basically makes the display of both the list, and the pop-up 
    function draw(data) {
    // every notification is iterated through (popup)
    data.popup.forEach(showModal);
  }

    function showModal(alert) {
        // no dulicate allowed
        if (shown.has(alert.id)) return;
        // adds the pop-up
        shown.add(alert.id);
        // optimistic update, expects to be dismissed
        fetch('api/notification_seen.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: alert.id })
        });
        // this is the part where the actual 'ui' is displayed
        const ov = document.createElement('div');
        ov.className = 'notify-overlay';
        ov.innerHTML = `
    <div class="notify-modal">
      <h3>${alert.title}</h3>
      <p>${alert.message}</p>
      <div class="notify-actions">
        <button class="btn btn-secondary">Dismiss</button>
      </div>
    </div>`;
        document.body.appendChild(ov);

        // The button action, basically just removes it
        ov.querySelector('.btn').onclick = () => {
            document.body.removeChild(ov);
        };
    }
})();