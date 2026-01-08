document.addEventListener('DOMContentLoaded', function () {
    loadMissions();

    document.getElementById('addMissionBtn').addEventListener('click', function () {
        Swal.fire({
            title: 'Add Mission',
            html: `
                <input type="text" id="missionName" class="swal2-input" placeholder="Mission Name" autocomplete="off" required>
                <input type="number" id="missionReward" class="swal2-input" placeholder="Mission Reward" autocomplete="off" min="0" required>
                <textarea id="missionDescription" class="swal2-textarea" placeholder="Mission Description" autocomplete="off" required></textarea>
            `,
            confirmButtonText: 'Add',
            preConfirm: () => {
                const name = document.getElementById('missionName').value.trim();
                const reward = document.getElementById('missionReward').value.trim();
                const description = document.getElementById('missionDescription').value.trim();

                if (!name || reward === "" || !description) {
                    Swal.showValidationMessage('All fields are required and must not be empty');
                    return false;
                }

                if (reward < 0) {
                    Swal.showValidationMessage('Mission Reward must be a number greater than or equal to 0');
                    return false;
                }

                return { name, reward, description };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                addMission(result.value);
            }
        });
    });
});

function showAddTaskModal(missionId) {
    Swal.fire({
        title: 'Add Task',
        html: `
            <input type="text" id="taskName" class="swal2-input" placeholder="Task Name" autocomplete="off" required>
            <input type="text" id="taskChatId" class="swal2-input" placeholder="Chat Username (No @)" autocomplete="off" required>
            <input type="text" id="taskUrl" class="swal2-input" placeholder="URL | ChatID" autocomplete="off" required>
            <div>
                <input type="radio" id="taskTypeUrl" name="taskType" class="swal2-radio" value="url" required>
                <label for="taskTypeUrl" class="swal2-label">Visit Website</label>
            </div>
            <div>
                <input type="radio" id="taskTypeJoinChat" name="taskType" class="swal2-radio" value="joinchat" required>
                <label for="taskTypeJoinChat" class="swal2-label">Join Chat</label>
            </div>
        `,
        confirmButtonText: 'Add',
        preConfirm: () => {
            const name = document.getElementById('taskName').value.trim();
            const chatId = document.getElementById('taskChatId').value.trim();
            const url = document.getElementById('taskUrl').value.trim();
            const type = document.querySelector('input[name="taskType"]:checked').value;

            if (!name || !chatId || !url || !type) {
                Swal.showValidationMessage('All fields are required and must not be empty');
                return false;
            }

            // Convert type back to database format
            const databaseType = (type === 'url') ? 0 : 1;

            return { missionId, name, chatId, url, type: databaseType };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            addTask(result.value);
        }
    });
}

// Get CSRF token from meta tag or global variable
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken || '';

/**
 * Escape HTML entities to prevent XSS attacks
 * @param {string} text - Text to escape
 * @returns {string} Escaped text safe for HTML insertion
 */
function escapeHtml(text) {
    if (text == null) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function addTask(task) {
    fetch('api.php?action=addTask', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({...task, csrf_token: csrfToken})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadMissions();
            Swal.fire('Success', 'Task added successfully', 'success');
        } else {
            Swal.fire('Error', 'Failed to add task', 'error');
        }
    });
}

function loadMissions() {
    fetch('api.php?action=getMissions')
        .then(response => response.json())
        .then(data => {
            const missionsList = document.getElementById('missionsList');
            missionsList.innerHTML = '';

            data.missions.forEach(mission => {
                const missionElement = document.createElement('div');
                missionElement.classList.add('bg-white', 'p-4', 'rounded', 'shadow');
                
                // Create elements using DOM methods to prevent XSS
                const missionName = document.createElement('h2');
                missionName.classList.add('text-2xl', 'font-bold');
                missionName.textContent = mission.name || '';
                
                const rewardPara = document.createElement('p');
                rewardPara.textContent = `Reward: ${mission.reward || 0}`;
                
                const descriptionPara = document.createElement('p');
                descriptionPara.textContent = mission.description || '';
                
                const removeBtn = document.createElement('button');
                removeBtn.classList.add('bg-red-500', 'text-white', 'px-2', 'py-1', 'rounded', 'mt-2');
                removeBtn.textContent = 'Remove Mission';
                removeBtn.onclick = () => removeMission(mission.id);
                
                const addTaskBtn = document.createElement('button');
                addTaskBtn.classList.add('bg-green-500', 'text-white', 'px-2', 'py-1', 'rounded', 'mt-2');
                addTaskBtn.textContent = 'Add Task';
                addTaskBtn.onclick = () => showAddTaskModal(mission.id);
                
                const tasksContainer = document.createElement('div');
                tasksContainer.classList.add('mt-4');
                
                const tasksTitle = document.createElement('h3');
                tasksTitle.classList.add('text-xl', 'font-bold');
                tasksTitle.textContent = 'Tasks';
                
                const tasksList = document.createElement('ul');
                tasksList.classList.add('list-disc', 'pl-4');
                
                mission.tasks.forEach(task => {
                    const taskItem = document.createElement('li');
                    
                    const taskNamePara = document.createElement('p');
                    taskNamePara.textContent = task.name || '';
                    taskItem.appendChild(taskNamePara);
                    
                    if (task.type == 1) {
                        const usernamePara = document.createElement('p');
                        usernamePara.textContent = `Username: ${task.chatId || ''}`;
                        taskItem.appendChild(usernamePara);
                        
                        const chatIdPara = document.createElement('p');
                        chatIdPara.textContent = `ChatID: ${task.url || ''}`;
                        taskItem.appendChild(chatIdPara);
                    } else {
                        const websitePara = document.createElement('p');
                        websitePara.textContent = `webSite: ${task.url || ''}`;
                        taskItem.appendChild(websitePara);
                    }
                    
                    const typePara = document.createElement('p');
                    typePara.textContent = `Type: ${task.type == 0 ? 'WebSite' : 'Join Chat'}`;
                    taskItem.appendChild(typePara);
                    
                    const removeTaskBtn = document.createElement('button');
                    removeTaskBtn.classList.add('bg-red-500', 'text-white', 'px-2', 'py-1', 'rounded', 'mt-2');
                    removeTaskBtn.textContent = 'Remove Task';
                    removeTaskBtn.onclick = () => removeTask(task.id);
                    taskItem.appendChild(removeTaskBtn);
                    
                    tasksList.appendChild(taskItem);
                });
                
                tasksContainer.appendChild(tasksTitle);
                tasksContainer.appendChild(tasksList);
                
                missionElement.appendChild(missionName);
                missionElement.appendChild(rewardPara);
                missionElement.appendChild(descriptionPara);
                missionElement.appendChild(removeBtn);
                missionElement.appendChild(addTaskBtn);
                missionElement.appendChild(tasksContainer);
                
                missionsList.appendChild(missionElement);
            });
        });
}


function addMission(mission) {
    fetch('api.php?action=addMission', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({...mission, csrf_token: csrfToken})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadMissions();
            Swal.fire('Success', 'Mission added successfully', 'success');
        } else {
            Swal.fire('Error', 'Failed to add mission', 'error');
        }
    });
}

function removeMission(id) {
    fetch('api.php?action=removeMission', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ id: id, csrf_token: csrfToken })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadMissions();
                Swal.fire('Success', 'Mission removed successfully', 'success');
            } else {
                Swal.fire('Error', 'Failed to remove mission', 'error');
            }
        });
}

function removeTask(id) {
    fetch('api.php?action=removeTask', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ id: id, csrf_token: csrfToken })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadMissions();
                Swal.fire('Success', 'Task removed successfully', 'success');
            } else {
                Swal.fire('Error', 'Failed to remove task', 'error');
            }
        });
}
