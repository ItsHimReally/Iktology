<?php
require_once "php/db.php";
require_once "php/Parsedown.php";
session_start();
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
$link = connectDB(); $is4Doc = false; $isSum = false;
if (!isset($_SESSION['chats'])) {
    $_SESSION['chats'] = [];
}

if (isset($_GET["q"]) and !isset($_GET["a"])) {
	header("location: /?a=new&q=".htmlspecialchars($_GET["q"]));
	exit();
}

if (is_numeric($_GET["doc"])) {
	$q = mysqli_query($link, "SELECT * FROM docs WHERE id = ".$_GET["doc"]." LIMIT 1");
	$d = mysqli_fetch_array($q);
}

function addChat($title, $messages, $doc, $summary) {
    $chat = [
        'id' => uniqid(),
        'created_at' => date('Y-m-d H:i:s'),
        'title' => $title,
        'messages' => [],
	    'doc' => $doc,
	    'summary' => $summary
    ];

    foreach ($messages as $message) {
        $chat['messages'][] = [
            'role' => $message['role'],
            'message' => $message['message'],
            'time' => date('Y-m-d H:i:s')
        ];
    }

    $_SESSION['chats'][] = $chat;
    return $chat['id'];
}
function addMessageToChat($chatId, $messages) {
    foreach ($_SESSION['chats'] as &$chat) {
        if ($chat['id'] == $chatId) {
            foreach ($messages as $message) {
                $chat['messages'][] = [
                    'role' => $message['role'],
                    'message' => $message['message'],
                    'time' => date('Y-m-d H:i:s')
                ];
            }
            break;
        }
    }
}

function displayChatTitles() {
    if (empty($_SESSION['chats'])) {
        echo '<div class="noTalks">Чатов пока что нет</div>';
    } else {
        foreach ($_SESSION['chats'] as $chat) {
            $class = "";
            if (isset($_GET["id"])) {
                if ($_GET["id"] == $chat["id"]) {
                    $class = ' class="there"';
                }
            }
            echo '<a href="?id=' . $chat['id'] . '"'.$class.'>' . htmlspecialchars($chat['title']) . '</a>';
        }
    }
}

function displayChatMessages($chatId) {
	global $is4Doc, $isSum;
    foreach ($_SESSION['chats'] as $chat) {
        if ($chat['id'] == $chatId) {
			if (!is_null($chat["doc"])) {
                $is4Doc = $chat["doc"];
			}
			if (!is_null($chat["summary"])) {
                $isSum = true;
			}
            foreach ($chat['messages'] as $message) {
                if ($message['role'] == 'assistant') {
                    $text = str_replace("**Источники:**\n\n", "**Источники:**\n", $message['message']);
                    $text = str_replace("\n", "<br>", $text);
                    $parsedown = new Parsedown();
                    $msg = $parsedown->text($text);
                    echo '<div class="message sAI">
                             <div class="avatar">
                                  AI
                             </div>
                             <div class="text">
                                  ' . $msg . '
                             </div>
                          </div>';
                } else if ($message['role'] == 'user') {
                    echo '<div class="message s1">
                             <div class="text">
                                  ' . htmlspecialchars($message['message']) . '
                             </div>
                          </div>';
                }
            }
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['a']) && $_GET['a'] == 'send') {
    $roles = $_POST['role'];
    $messages = $_POST['message'];
	$doc = $_POST["doc"] ?? null;
    $summary = $_POST["summary"] ?? null;
    $chatMessages = [];

    foreach ($roles as $index => $role) {
        $chatMessages[] = [
            'role' => $role,
            'message' => $messages[$index]
        ];
    }

    if (isset($_GET['id'])) {
        $chatId = $_GET['id'];
        if ($chatId == 'new') {
            // Заголовком является первое сообщение
            $title = $messages[0];
            $newChatId = addChat($title, $chatMessages, $doc, $summary);
            echo json_encode(['chat_id' => $newChatId]);
            exit();
        } else {
            // Добавляем сообщения в существующий чат
            addMessageToChat($chatId, $chatMessages);
            echo json_encode(['chat_id' => $chatId]);
            exit();
        }
    }
}



//// Пример добавления нового чата
//addChat('Тестовый чат', [
//    ['role' => 'user', 'message' => 'Привет!'],
//    ['role' => 'assistant', 'message' => 'Здравствуйте!']
//]);

//// Пример добавления сообщения в существующий чат
//if (!empty($_SESSION['chats'])) {
//    $chatId = $_SESSION['chats'][0]['id'];
//    addMessageToChat($chatId, array(array(
//		"role" => "user",
//	    "message" => "LOL"
//    )));
//}
//
//var_dump($_SESSION);
?>
<html>
    <head>
        <title>Iktology</title>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="css/style.css" media="all">
	    <link rel="stylesheet" href="css/dia.css" media="all">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="Description" content="Iktology">
        <meta http-equiv="Content-language" content="ru-RU">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;700;900&family=Roboto&display=swap" rel="stylesheet">
    </head>
    <body>
    <div class="wrapper">
        <div class="sideBar">
            <div class="sb_title">
	            <span class="icon">Iktology</span>
	            <a href="/settings">
		            <img src="images/gear.svg" alt="Settings">
	            </a>
            </div>
	        <div class="sb_type">
		        <a href="/" class="here">Ассистент</a>
		        <a href="/docs">База знаний</a>
	        </div>
	        <div class="sb_talks">
		        <a href="?a=new" class="new">
			        <img src="images/plus-circle.svg" alt="New">
			        Новый чат
		        </a>
		        <?php displayChatTitles(); ?>
	        </div>
        </div>
	    <div class="page">
        <?php if (($_GET["a"] == "new" or isset($_GET["id"])) and !isset($_GET["doc"])): ?>
		    <div class="chat">
			    <div class="msglist" id="chat_messages">
                    <?php if (isset($_GET["id"])) { displayChatMessages($_GET["id"]); } ?>
			    </div>
			    <div class="send">
				    <?php if ((is_numeric($_GET["doc"]) and $_GET["a"] == "new") or $is4Doc): ?>
					    <?php
                        if (is_numeric($is4Doc)) {
                            $q = mysqli_query($link, "SELECT * FROM docs WHERE id = ".$is4Doc." LIMIT 1");
                            $d = mysqli_fetch_array($q);
                        }
					    ?>
					<a class="chat4doc" href="/docs?id=<?=$d["id"]?>" target="_blank">
						<img src="/images/file-earmark-text-fill.svg" alt="File">
						<?=$d["title"]?>
					</a>
				    <?php endif; ?>
				    <?php if (!$isSum): ?>
				    <form id="form_chat" data-chatid="<?php echo $_GET['id'] ?? 'new'; ?>">
					    <textarea id="message_input" placeholder="Введите сообщение" required><?php echo htmlspecialchars($_GET['q']) ?? ''; ?></textarea><br>
					    <button type="submit">
						    <img src="/images/send.svg" alt="Отправить">
					    </button>
				    </form>
				    <?php endif; ?>
			    </div>
		    </div>
	        <script>
                document.getElementById('form_chat').addEventListener('submit', async function(event) {
                    event.preventDefault();

                    const form = event.target;
                    const messageInput = document.getElementById('message_input');
                    const chatTitleInput = document.getElementById('chat_title');
                    const chatId = form.getAttribute('data-chatid');
                    const messageText = messageInput.value;

                    // Отобразить новое сообщение пользователя в чате
                    const userMessage = document.createElement('div');
                    userMessage.className = 'message s1';
                    userMessage.innerHTML = `<div class="text">${messageText}</div>`;
                    document.getElementById('chat_messages').appendChild(userMessage);

                    // Заморозить поле ввода
                    messageInput.disabled = true;

                    // Отобразить сообщение от ассистента (загрузка)
                    const assistantMessage = document.createElement('div');
                    assistantMessage.className = 'message sAI mnew';
                    assistantMessage.innerHTML = `<div class="avatar">AI</div><div class="text"><img class="loading" src="/images/svg.svg" alt="Loading..."></div>`;
                    document.getElementById('chat_messages').appendChild(assistantMessage);

                    // Отправить запрос в API
                    const response = await fetch(`http://176.109.110.121:8000/api/question?query=${encodeURIComponent(messageText)}`);
                    const data = await response.json();
                    const assistantReply = data.reply;

                    // Обновить сообщение ассистента
                    //assistantMessage.querySelector('.message').innerHTML = assistantReply;

                    // Подготовить данные для POST-запроса
                    const formData = new FormData();
                    formData.append('role[]', 'user');
                    formData.append('message[]', messageText);
                    formData.append('role[]', 'assistant');
                    formData.append('message[]', assistantReply);

                    if (chatId === 'new' && chatTitleInput) {
                        formData.append('title', chatTitleInput.value);
                    }

                    // Отправить POST-запрос на сервер
                    const postResponse = await fetch(`?a=send&id=${chatId}`, {
                        method: 'POST',
                        body: formData
                    });
                    const postData = await postResponse.json();
                    const newChatId = postData.chat_id;

                    // Перенаправить пользователя на новый чат
                    window.location.href = `?id=${newChatId}`;
                });

                document.getElementById('message_input').addEventListener('keydown', function(event) {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        document.getElementById('form_chat').dispatchEvent(new Event('submit', { 'bubbles': true }));
                    }
                });

                document.addEventListener("DOMContentLoaded", function() {
                    window.scrollTo(0, document.body.scrollHeight);
                });
	        </script>
        <?php elseif (isset($_GET["doc"]) and $_GET["a"] == "summary"): ?>
	        <div class="chat">
		        <div class="send">
			        <div class="msglist" id="chat_messages"></div>
			        <a class="chat4sum" href="/docs?id=<?=$d["id"]?>" target="_blank">
				        <img src="/images/stars.svg" alt="File">
                        <?=$d["title"]?>
			        </a>
			        <form id="form_chat" data-chatid="<?php echo $_GET['id'] ?? 'new'; ?>">
				        <textarea id="message_input" disabled required>/summary</textarea><br>
				        <button type="submit">
					        <img src="/images/send.svg" alt="Отправить">
				        </button>
			        </form>
		        </div>
	        </div>
	        <script>
                document.getElementById('form_chat').addEventListener('submit', async function(event) {
                    event.preventDefault();

                    const form = event.target;
                    const messageInput = document.getElementById('message_input');
                    const chatTitleInput = document.getElementById('chat_title');
                    const chatId = form.getAttribute('data-chatid');
                    const messageText = messageInput.value;

                    // Отобразить новое сообщение пользователя в чате
                    const userMessage = document.createElement('div');
                    userMessage.className = 'message s1';
                    userMessage.innerHTML = `<div class="text">${messageText}</div>`;
                    document.getElementById('chat_messages').appendChild(userMessage);

                    // Заморозить поле ввода
                    messageInput.disabled = true;

                    // Отобразить сообщение от ассистента (загрузка)
                    const assistantMessage = document.createElement('div');
                    assistantMessage.className = 'message sAI mnew';
                    assistantMessage.innerHTML = `<div class="avatar">AI</div><div class="text"><img class="loading" src="/images/svg.svg" alt="Loading..."></div>`;
                    document.getElementById('chat_messages').appendChild(assistantMessage);

                    const mdResponse = await fetch('<?=$d["srcTxt"]?>');
                    const mdText = await mdResponse.text();

                    const fd = new FormData();
                    fd.append('text', mdText);
                    const response = await fetch(`http://176.109.110.121:8000/api/summarize`, {
                        method: 'POST',
                        body: fd
                    });
                    const data = await response.json();
                    const assistantReply = data.reply;

                    // Обновить сообщение ассистента
                    //assistantMessage.querySelector('.message').innerHTML = assistantReply;

                    // Подготовить данные для POST-запроса
                    const formData = new FormData();
                    formData.append('summary', 'true');
                    formData.append('role[]', 'user');
                    formData.append('message[]', messageText);
                    formData.append('role[]', 'assistant');
                    formData.append('message[]', assistantReply);

                    if (chatId === 'new' && chatTitleInput) {
                        formData.append('title', `<?=$d["title"]?>`);
                    }

                    // Отправить POST-запрос на сервер
                    const postResponse = await fetch(`?a=send&id=${chatId}`, {
                        method: 'POST',
                        body: formData
                    });
                    const postData = await postResponse.json();
                    const newChatId = postData.chat_id;

                    // Перенаправить пользователя на новый чат
                    window.location.href = `?id=${newChatId}`;
                });

                document.getElementById('message_input').addEventListener('keydown', function(event) {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        document.getElementById('form_chat').dispatchEvent(new Event('submit', { 'bubbles': true }));
                    }
                });

                document.addEventListener("DOMContentLoaded", function() {
                    window.scrollTo(0, document.body.scrollHeight);
                });
	        </script>
        <?php elseif (isset($_GET["doc"]) and $_GET["a"] == "new"): ?>
	        <div class="chat">
		        <div class="msglist" id="chat_messages">
                    <?php if (isset($_GET["id"])) { displayChatMessages($_GET["id"]); } ?>
		        </div>
		        <div class="send">
                    <?php if ((is_numeric($_GET["doc"])) or $is4Doc): ?>
				        <a class="chat4doc" href="/docs?id=<?=$d["id"]?>" target="_blank">
					        <img src="/images/file-earmark-text-fill.svg" alt="File">
                            <?=$d["title"]?>
				        </a>
                    <?php endif; ?>
			        <form id="form_chat" data-chatid="<?php echo $_GET['id'] ?? 'new'; ?>">
				        <textarea id="message_input" placeholder="Введите сообщение" required><?php echo htmlspecialchars($_GET['q']) ?? ''; ?></textarea><br>
				        <button type="submit">
					        <img src="/images/send.svg" alt="Отправить">
				        </button>
			        </form>
		        </div>
	        </div>
	        <script>
                document.getElementById('form_chat').addEventListener('submit', async function(event) {
                    event.preventDefault();

                    const form = event.target;
                    const messageInput = document.getElementById('message_input');
                    const chatTitleInput = document.getElementById('chat_title');
                    const chatId = form.getAttribute('data-chatid');
                    const messageText = messageInput.value;

                    // Отобразить новое сообщение пользователя в чате
                    const userMessage = document.createElement('div');
                    userMessage.className = 'message s1';
                    userMessage.innerHTML = `<div class="text">${messageText}</div>`;
                    document.getElementById('chat_messages').appendChild(userMessage);

                    // Заморозить поле ввода
                    messageInput.disabled = true;

                    // Отобразить сообщение от ассистента (загрузка)
                    const assistantMessage = document.createElement('div');
                    assistantMessage.className = 'message sAI mnew';
                    assistantMessage.innerHTML = `<div class="avatar">AI</div><div class="text"><img class="loading" src="/images/svg.svg" alt="Loading..."></div>`;
                    document.getElementById('chat_messages').appendChild(assistantMessage);

                    // Запрос на содержимое md файла
                    const mdResponse = await fetch('<?=$d["srcTxt"]?>');
                    const mdText = await mdResponse.text();

                    // Получаем куки PHPSESSID
                    const sID = document.cookie.split('; ').find(row => row.startsWith('PHPSESSID')).split('=')[1];

                    const fd = new FormData();
                    fd.append('text', mdText);

                    // Отправка второго POST запроса с query и sID
                    const secondPostResponse = await fetch(`http://176.109.110.121:8000/api/doc_processing?query=${encodeURIComponent(messageText)}&sID=${sID}`, {
                        method: 'POST',
                        body: fd
                    });

                    const data = await secondPostResponse.json();
                    const assistantReply = data.reply;

                    // Обновляем сообщение ассистента
                    //assistantMessage.querySelector('.text').innerHTML = assistantReply;

                    // Обновить сообщение ассистента
                    //assistantMessage.querySelector('.message').innerHTML = assistantReply;

                    // Подготовить данные для POST-запроса
                    const formData = new FormData();
                    formData.append('doc', '<?=$d["id"]?>');
                    formData.append('role[]', 'user');
                    formData.append('message[]', messageText);
                    formData.append('role[]', 'assistant');
                    formData.append('message[]', assistantReply);

                    if (chatId === 'new' && chatTitleInput) {
                        formData.append('title', chatTitleInput.value);
                    }

                    // Отправить POST-запрос на сервер
                    const postResponse = await fetch(`?a=send&id=${chatId}`, {
                        method: 'POST',
                        body: formData
                    });
                    const postData = await postResponse.json();
                    const newChatId = postData.chat_id;

                    // Перенаправить пользователя на новый чат
                    window.location.href = `?id=${newChatId}`;
                });

                document.getElementById('message_input').addEventListener('keydown', function(event) {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        document.getElementById('form_chat').dispatchEvent(new Event('submit', { 'bubbles': true }));
                    }
                });

                document.addEventListener("DOMContentLoaded", function() {
                    window.scrollTo(0, document.body.scrollHeight);
                });
	        </script>
        <?php else: ?>
	        <div class="pg-center">
		        <div class="titleFlex">
			        <div class="title large">Iktology</div>
			        <div class="stitle">Получите точный ответ по нормативной документации</div>
		        </div>
		        <div class="pc-content">
			        <form class="search" method="GET" action="?a=new">
				        <label>
					        <input type="text" name="q" placeholder="Чем могу помочь?">
				        </label>
				        <button>
					        <img src="/images/send.svg" alt="Отправить">
				        </button>
			        </form>
			        <span class="comment">Может занять некоторое время.<br>Создано для демонстрации. Более точный результат при запуске скриптов локально.</span>
		        </div>
	        </div>
        <?php endif; ?>
	    </div>
    </div>
    </body>
</html>
