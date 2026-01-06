<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RubitoChat</title>
    <style>

        body { font-family: Verdana, Helvetica, Arial, sans-serif; background-color: #f5f7fa; margin: 0; padding: 0; font-size: 12px; color: #536482; }
        

        .header { background: #0076b1; color: white; padding: 10px 15px; display: flex; justify-content: space-between; border-bottom: 4px solid #005a8c; }
        .header h1 { margin: 0; font-size: 18px; }


        .controls { background: #eef5f9; padding: 5px 10px; border-bottom: 1px solid #c8d3dc; display: flex; gap: 10px; align-items: center; }
        button, input[type=text] { padding: 3px; font-size: 11px; border: 1px solid #999; cursor: pointer; }
        

        .main-container { display: flex; height: 450px; padding: 10px; gap: 10px; }
        

        #chatList { flex: 3; background: #eef5f9; border: 1px solid #c2c2c2; overflow-y: scroll; padding: 5px; list-style: none; margin: 0; }
        #chatList li { margin-bottom: 4px; border-bottom: 1px dotted #ccc; padding-bottom: 2px; }
        

        .sidebar { flex: 1; display: flex; flex-direction: column; gap: 10px; min-width: 180px; }
        .box { border: 1px solid #c2c2c2; background: white; flex: 1; display: flex; flex-direction: column; }
        .box-header { background: #0076b1; color: white; text-align: center; padding: 5px; font-weight: bold; }
        .box-content { padding: 10px; overflow-y: auto; background: #ecf3f7; flex-grow: 1; }
        
        .list-item { cursor: pointer; padding: 2px 0; }
        .list-item:hover { text-decoration: underline; }
        
        .role-admin { color: #aa0000; font-weight: bold; }
        .role-user { color: #536482; }

        .input-area { padding: 10px; border-top: 1px solid #ccc; background: #fff; }
        .emoji-bar { margin-bottom: 8px; display: flex; flex-wrap: wrap; gap: 4px; }
        .emoji-bar img { width: 18px; height: 18px; cursor: pointer; border: 1px solid transparent; }
        .emoji-bar img:hover { transform: scale(1.3); border-color: #0076b1; }
        textarea { width: 100%; height: 50px; border: 1px solid #999; resize: none; margin-top: 5px; box-sizing: border-box; }


        #loginModal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; justify-content: center; align-items: center; z-index: 999; }
        .modal-box { background: white; padding: 20px; border-radius: 5px; width: 300px; text-align: center; border: 2px solid #0076b1; }
        .modal-box input { width: 80%; padding: 5px; margin: 10px 0; font-size: 14px; }
        .modal-box button { padding: 5px 15px; background: #0076b1; color: white; border: none; }
    </style>
</head>
<body>

<div id="loginModal">
    <div class="modal-box">
        <h2>Giriş Yap</h2>
        <input type="text" id="nicknameInput" placeholder="Kullanıcı Adı" maxlength="15">
        <br>
        <button onclick="login()">Sohbete Başla</button>
        <p style="font-size:10px; color:red; margin-top:10px;" id="loginError"></p>
    </div>
</div>

<div class="header">
    <h1>Rubito Chat</h1>
    <div class="header-right">Powered by PHP & JSON</div>
</div>

<div class="controls">
    <button onclick="location.reload()">Logout</button>
    <span>Aktif Kanal: <b id="lblChannel">[Genel]</b></span>
    <div style="flex-grow:1"></div>
    <input type="text" id="newChannelName" placeholder="Yeni Kanal Adı" style="width:100px;">
    <button onclick="createChannel()">+ Kanal Ekle</button>
</div>

<div class="main-container">
    <ul id="chatList"><li>Yükleniyor...</li></ul>
    <div class="sidebar">
        <div class="box">
            <div class="box-header">Online Users</div>
            <div class="box-content" id="userList"></div>
        </div>
        <div class="box">
            <div class="box-header">Kanallar</div>
            <div class="box-content" id="channelList"></div>
        </div>
    </div>
</div>

<div class="input-area">
    <div class="emoji-bar" id="emojiBar"></div>
    <textarea id="messageInput" placeholder="Mesajınızı yazın..."></textarea>
    <div style="text-align:right; margin-top:5px;">
        <button onclick="sendMessage()" style="padding: 5px 20px; font-weight: bold;">GÖNDER</button>
    </div>
</div>

<script>
    let currentNick = "";
    let currentChannel = "Genel";
    const chatList = document.getElementById('chatList');
    const userList = document.getElementById('userList');
    const channelList = document.getElementById('channelList');
    const messageInput = document.getElementById('messageInput');

    const emojiMap = {
        'O:)': 'angel.png', ':?': 'confused.png', '8)': 'cool.png', ':cry:': 'crying.png',
        ':dev:': 'devilish.png', '8o': 'eek.png', ':err:': 'error.png', ':fav:': 'favorite.png',
        ':glass:': 'glasses.png', ':grin:': 'grin.png', ':help:': 'help.png', ':idea:': 'idea.png',
        ':important:': 'important.png', ':kiss:': 'kiss.png', ':monkey:': 'monkey.png',
        ':x': 'plain.png', ':P': 'razz.png', ':(': 'sad.png', ':D': 'smile-big.png',
        ':)': 'smile.png', ':o': 'surprise.png', ':warn:': 'warning.png', ';)': 'wink.png'
    };

    const emojiBar = document.getElementById('emojiBar');
    Object.entries(emojiMap).forEach(([code, file]) => {
        const img = document.createElement('img');
        img.src = `smiles/${file}`;
        img.title = code;
        img.onclick = () => addEmoji(code);
        emojiBar.appendChild(img);
    });


    function login(passInput = "") {
        const nick = document.getElementById('nicknameInput').value;
        if(!nick) return;

        const formData = new FormData();
        formData.append('nick', nick);
        if(passInput) formData.append('pass', passInput);

        fetch('api.php?action=login', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    currentNick = data.nick;
                    document.getElementById('loginModal').style.display = 'none';
                    fetchData();
                    setInterval(fetchData, 2000);
                } else if (data.require_pass) {
                    // API config.php'deki admin ismiyle eşleşince bu alanı tetikler
                    const pass = prompt(data.msg);
                    if (pass !== null) login(pass); 
                } else { 
                    document.getElementById('loginError').innerText = data.msg; 
                }
            });
    }

    function fetchData() {
        fetch(`api.php?action=read&channel=${encodeURIComponent(currentChannel)}`)
            .then(r => r.json())
            .then(data => {
                chatList.innerHTML = '';
                data.messages.forEach(msg => {
                    const li = document.createElement('li');
                    li.innerHTML = `<span style="color:#666; font-size:10px;">(${msg.time})</span> 
                                    <b style="color:${msg.color};">${msg.user}:</b> 
                                    ${parseMessage(msg.text)}`;
                    chatList.appendChild(li);
                });
                chatList.scrollTop = chatList.scrollHeight;

                userList.innerHTML = '';
                data.users.forEach(u => {
                    let cls = u.role === 'admin' ? 'role-admin' : 'role-user';
                    userList.innerHTML += `<div class="${cls}">• ${u.name}</div>`;
                });

                channelList.innerHTML = '';
                data.channels.forEach(ch => {
                    let style = ch === currentChannel ? 'font-weight:bold; color:#0076b1;' : '';
                    channelList.innerHTML += `<div class="list-item" style="${style}" onclick="switchChannel('${ch}')"># ${ch}</div>`;
                });
            });
    }

    function sendMessage() {
        const text = messageInput.value;
        if (!text.trim()) return;
        const formData = new FormData();
        formData.append('text', text);
        formData.append('channel', currentChannel);
        fetch('api.php?action=send', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') { messageInput.value = ''; fetchData(); }
                else { alert(res.msg); }
            });
    }

    function parseMessage(text) {
        let clean = text.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        Object.entries(emojiMap).forEach(([code, file]) => {
            let escapedCode = code.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const img = `<img src="smiles/${file}" style="vertical-align:middle; width:16px;" alt="${code}">`;
            clean = clean.replace(new RegExp(escapedCode, 'g'), img);
        });
        return clean;
    }

    function addEmoji(code) {
        messageInput.value += ` ${code} `;
        messageInput.focus();
    }

    function switchChannel(name) {
        currentChannel = name;
        document.getElementById('lblChannel').innerText = '[' + name + ']';
        fetchData();
    }

    function createChannel() {
        const name = document.getElementById('newChannelName').value;
        if(!name) return;
        const formData = new FormData();
        formData.append('name', name);
        fetch('api.php?action=create_channel', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    document.getElementById('newChannelName').value = '';
                    switchChannel(name);
                } else { alert(res.msg); }
            });
    }

    messageInput.addEventListener('keypress', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
</script>
</body>
</html>