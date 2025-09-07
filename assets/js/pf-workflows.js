/**
 * PromptFinder - Simple Variable System
 * NO WordPress complexity - just works!
 */

console.log('PF Simple loaded!');
console.log('PF Simple: Script is running immediately!');

// Global variable store
const PF_VARS = {};

// IMMEDIATE TEST - does this script even load?
console.log('PF Simple: Script loaded successfully - DOM ready check starting...');

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('PF Simple: DOM ready');
    initVariableSystem();
});

function initVariableSystem() {
    console.log('PF Simple: Initializing variable system');
    
    // Find all variable inputs
    const variableInputs = document.querySelectorAll('input[data-var-name]');
    console.log('PF Simple: Found', variableInputs.length, 'variable inputs');
    
    // DEBUG: Log all inputs found
    variableInputs.forEach(function(input, index) {
        console.log(`PF Simple: Input ${index}:`, {
            element: input,
            varName: input.getAttribute('data-var-name'),
            currentValue: input.value,
            type: input.type
        });
    });
    
    // Add event listeners to all variable inputs
    variableInputs.forEach(function(input) {
        const varName = input.getAttribute('data-var-name');
        console.log('PF Simple: Setting up input for variable:', varName);
        
        // Store initial value
        if (input.value) {
            PF_VARS[varName] = input.value;
            console.log('PF Simple: Stored initial value:', varName, '=', input.value);
        }
        
        // Add input event listener
        input.addEventListener('input', function() {
            console.log('PF Simple: Variable changed (input):', varName, '=', input.value);
            PF_VARS[varName] = input.value;
            console.log('PF Simple: PF_VARS after update:', PF_VARS);
            updateAllPrompts();
        });
        
        // Add change event listener (for dropdowns, etc.)
        input.addEventListener('change', function() {
            console.log('PF Simple: Variable changed (change):', varName, '=', input.value);
            PF_VARS[varName] = input.value;
            console.log('PF Simple: PF_VARS after update:', PF_VARS);
            updateAllPrompts();
        });
    });
    
    // Initial update
    console.log('PF Simple: Running initial update');
    updateAllPrompts();
}

function updateAllPrompts() {
    console.log('PF Simple: Updating all prompts with vars:', PF_VARS);
    
    // Find all prompt textareas
    const promptTextareas = document.querySelectorAll('textarea[data-prompt-template]');
    console.log('PF Simple: Found', promptTextareas.length, 'prompt textareas');
    
    // DEBUG: Log all textareas found
    promptTextareas.forEach(function(textarea, index) {
        console.log(`PF Simple: Textarea ${index}:`, {
            element: textarea,
            dataBase: textarea.getAttribute('data-base'),
            currentValue: textarea.value
        });
    });
    
    promptTextareas.forEach(function(textarea) {
        const baseTemplate = textarea.getAttribute('data-base');
        if (!baseTemplate) {
            console.log('PF Simple: No data-base found for textarea');
            return;
        }
        
        console.log('PF Simple: Updating prompt with template:', baseTemplate);
        
        // Replace variables in template
        let updatedPrompt = baseTemplate;
        
        // Find all {variable} patterns
        const variablePattern = /\{([^}]+)\}/g;
        let match;
        let replacements = [];
        
        while ((match = variablePattern.exec(baseTemplate)) !== null) {
            const varName = match[1];
            const varValue = PF_VARS[varName] || '';
            console.log('PF Simple: Replacing', varName, 'with', varValue);
            replacements.push({varName, varValue, original: match[0]});
            updatedPrompt = updatedPrompt.replace('{' + varName + '}', varValue);
        }
        
        console.log('PF Simple: All replacements:', replacements);
        console.log('PF Simple: Final updated prompt:', updatedPrompt);
        
        // Update textarea value
        textarea.value = updatedPrompt;
        console.log('PF Simple: Updated textarea value to:', textarea.value);
    });
}

// Copy to clipboard function
function copyToClipboard(textarea) {
    textarea.select();
    document.execCommand('copy');
    
    // Show feedback
    const button = textarea.nextElementSibling;
    if (button && button.classList.contains('pf-copy-btn')) {
        const originalText = button.textContent;
        button.textContent = 'Copied!';
        button.style.backgroundColor = '#28a745';
        
        setTimeout(function() {
            button.textContent = originalText;
            button.style.backgroundColor = '';
        }, 2000);
    }
}

// Make copyToClipboard globally available
window.copyToClipboard = copyToClipboard;

console.log('PF Simple: Script loaded successfully');
