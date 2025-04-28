document.addEventListener('DOMContentLoaded', function() {
    // Theme toggling
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    const icon = themeToggleBtn.querySelector('i');
    let darkMode = localStorage.getItem('darkMode') === 'true';
    
    // Apply saved theme
    if (darkMode) {
        applyDarkMode();
    }
    
    themeToggleBtn.addEventListener('click', function() {
        darkMode = !darkMode;
        localStorage.setItem('darkMode', darkMode);
        
        if (darkMode) {
            applyDarkMode();
        } else {
            applyLightMode();
        }
    });
    
    function applyDarkMode() {
        document.documentElement.style.setProperty('--primary-color', '#ff7f50');
        document.documentElement.style.setProperty('--secondary-color', '#1a1a1a');
        document.documentElement.style.setProperty('--text-color', '#f5f5f5');
        document.documentElement.style.setProperty('--border-color', '#333');
        document.documentElement.style.setProperty('--card-bg', '#2a2a2a');
        document.documentElement.style.setProperty('--header-bg', '#2a2a2a');
        document.documentElement.style.setProperty('--hover-color', '#3a3a3a');
        icon.classList.replace('fa-moon', 'fa-sun');
    }
    
    function applyLightMode() {
        document.documentElement.style.setProperty('--primary-color', '#ff7f50');
        document.documentElement.style.setProperty('--secondary-color', '#f8f9fa');
        document.documentElement.style.setProperty('--text-color', '#333');
        document.documentElement.style.setProperty('--border-color', '#e0e0e0');
        document.documentElement.style.setProperty('--card-bg', '#fff');
        document.documentElement.style.setProperty('--header-bg', '#fff');
        document.documentElement.style.setProperty('--hover-color', '#f5f5f5');
        icon.classList.replace('fa-sun', 'fa-moon');
    }
    
    // Filter functionality
    const filterSelect = document.getElementById('filter-type');
    const tableRows = document.querySelectorAll('tbody tr');
    
    filterSelect.addEventListener('change', function() {
        const selectedValue = this.value.toLowerCase();
        
        tableRows.forEach(row => {
            if (row.classList.contains('no-data')) return;
            
            const typeCell = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            
            if (selectedValue === 'all' || typeCell === selectedValue) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        updateShownCount();
    });
    
    // Search functionality
    const searchInput = document.getElementById('search-investments');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        tableRows.forEach(row => {
            if (row.classList.contains('no-data')) return;
            
            const nameCell = row.querySelector('td:first-child').textContent.toLowerCase();
            const typeCell = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            
            if (nameCell.includes(searchTerm) || typeCell.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        updateShownCount();
    });
    
    function updateShownCount() {
        const visibleRows = Array.from(tableRows).filter(row => 
            row.style.display !== 'none' && !row.classList.contains('no-data')
        ).length;
        document.querySelector('.investment-list p').textContent = `Showing ${visibleRows} investments`;
    }
    
    // Modal functionality
    const modal = document.getElementById('investment-modal');
    const addBtn = document.querySelector('.add-investment-btn');
    const closeBtn = document.querySelector('.close');
    const cancelBtn = document.querySelector('.cancel-btn');
    const form = document.getElementById('investment-form');
    const modalTitle = document.getElementById('modal-title');
    const submitBtn = document.querySelector('.submit-btn');
    
    addBtn.addEventListener('click', function() {
        resetForm();
        modalTitle.textContent = 'Add New Investment';
        submitBtn.textContent = 'Add Investment';
        modal.style.display = 'block';
    });
    
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    cancelBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
    
    // Reset form fields
    function resetForm() {
        form.reset();
        document.getElementById('investment_id').value = '';
    }
    
    // Edit buttons
    const editButtons = document.querySelectorAll('.edit-btn');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            // Fetch investment details using AJAX
            fetch(`get_investment.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('investment_id').value = data.id;
                    document.getElementById('name').value = data.name;
                    document.getElementById('type').value = data.type_id;
                    document.getElementById('date').value = data.purchase_date;
                    document.getElementById('amount').value = data.amount;
                    document.getElementById('returns').value = data.expected_return;
                    document.getElementById('current_value').value = data.current_value;
                    
                    modalTitle.textContent = 'Edit Investment';
                    submitBtn.textContent = 'Update Investment';
                    modal.style.display = 'block';
                })
                .catch(error => console.error('Error fetching investment details:', error));
        });
    });
    
    // Delete buttons
    const deleteButtons = document.querySelectorAll('.delete-btn');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const row = this.closest('tr');
            const name = row.querySelector('td:first-child').textContent;
            
            if (confirm(`Are you sure you want to delete ${name}?`)) {
                // Send delete request using AJAX
                fetch('delete_investment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        row.remove();
                        updateShownCount();
                        
                        // Reload page to update stats and chart
                        window.location.reload();
                    } else {
                        alert('Error deleting investment: ' + data.message);
                    }
                })
                .catch(error => console.error('Error deleting investment:', error));
            }
        });
    });
    
    // Create investment distribution chart
    const ctx = document.getElementById('distributionChart').getContext('2d');
    
    // Fetch data for chart
    fetch('get_investment_distribution.php')
        .then(response => response.json())
        .then(data => {
            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return value === 0 ? '0' : 
                                       Math.floor(value).toString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 15,
                            padding: 15
                        }
                    }
                }
            };
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Invested',
                            backgroundColor: '#8c7ae6',
                            data: data.invested
                        },
                        {
                            label: 'Returns',
                            backgroundColor: '#7bed9f',
                            data: data.returns
                        }
                    ]
                },
                options: chartOptions
            });
        })
        .catch(error => console.error('Error fetching chart data:', error));
});

// This code should be in your script.js file
document.addEventListener('DOMContentLoaded', function() {
    const addInvestmentBtn = document.querySelector('.add-investment-btn');
    const investmentModal = document.getElementById('investment-modal');
    
    if (addInvestmentBtn) {
        addInvestmentBtn.addEventListener('click', function() {
            investmentModal.style.display = 'block';
        });
    }
});
// Add this to your script.js file to handle the modal open/close functionality
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('investment-modal');
    const addButton = document.querySelector('.add-investment-btn');
    const closeButton = document.querySelector('.close');
    const cancelButton = document.querySelector('.cancel-btn');
    
    // Open modal
    addButton.addEventListener('click', function() {
        modal.style.display = 'block';
    });
    
    // Close modal with X button
    closeButton.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    // Close modal with Cancel button
    cancelButton.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
});
