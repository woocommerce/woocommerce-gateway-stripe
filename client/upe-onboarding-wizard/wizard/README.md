# Wizard helpers
Use these components to simplify (hopefully) setting up a wizard.

The components are as follows:

### `wrapper/context`: `WizardContext`
#### Description:
Contains the data for the wizard. It just uses `React.createContext()`.

#### Attributes:
- `activeTask`: the identifier of the current tasks
- `completedTasks`: a key-value-pair of the completed tasks. The key is the task identifier, the value can be a boolean or an object, set through `setCompletedTasks`
- `setActiveTask`: helper to update the identifier of the current task - useful when navigating the wizard
- `setCompletedTasks`: helper to update the `completedTasks` attribute

#### Example of usage
```jsx
const Consumer = () => {
    const { setActiveTask } = useContext( WizardContext );
    
    return <button onClick={ () => setActiveTask( 'task-number-1' ) }>Go to task number 1</button>;
}
```

### `wrapper/index`: `Wizard`
#### Description:
Implementation of the `WizardContext`. Wrap all your components with this component.  
By leveraging the `WizardContext` you can instruct this component to update the values of the context provider.

#### props
- `children`: the React children hierarchy
- `defaultActiveTask` (recommended): the identifier of the first task that should be marked as active. Do not update this prop after initialization, it will have no effect.
- `defaultCompletedTasks` (optional): the key-value-pair of the completed tasks. Do not update this prop after initialization, it will have no effect.

#### Example of usage
```jsx
const Consumer = () => {
    return (
      <WizardTask defaultActiveTask="task-2" defaultCompletedTasks={{'task-1': true}}>
        <WizardTask id="task-1"><Task1Controls /></WizardTask>
        <WizardTask id="task-2"><Task2Controls /></WizardTask>
      </Wizard>
    );
}
```

### `task/context`: `WizardTaskContext`
#### Description:
Contains the data for one individual task within the wizard. It just uses `React.createContext()`.

#### Attributes:
- `isActive`: identifies whether or not the current task is active
- `setActive`: helper to mark the current task as active
- `isCompleted`: identifies whether or not the current task has been marked as completed
- `setCompleted`: helper to mark the current task as completed and (optionally) set the next active task

#### Example of usage
```jsx
const Consumer = () => {
  const { setActiveTask } = useContext( WizardContext );
  const { isActive, isCompleted, setCompleted } = useContext( WizardTaskContext );
    
    return (
      <div className={ classNames({'id-active': isActive, 'is-completed': isCompleted })}>
        <button onClick={ () => setCompleted( true, 'task-number-2' ) }>
          Mark current task complete and go to task number 2
        </button>
        <button onClick={ () => setActiveTask( 'task-number-3' ) }>
          Go to task number 3
        </button>
      </div>
    );
}
```

### `task/index`: `WizardTask`
#### Description:
Implementation of the `WizardTaskContext`. Wrap the componentry of one individual task with this component.  
By leveraging the `WizardTaskContext` you can instruct the `WizardTask` to update the values of the context provider.

#### props
- `children`: the React children hierarchy
- `id` (required): the identifier of this task

#### Example of usage
```jsx
const Task1 = () => {
    return (
      <WizardTask id="task-1">
        <Task1Controls />
      </WizardTask>
    );
}
```
