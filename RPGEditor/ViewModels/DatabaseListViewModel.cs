using System.Collections;
using System.Collections.ObjectModel;
using System.Windows.Input;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using RPGEditor.Models;

namespace RPGEditor.ViewModels;

public partial class DatabaseListViewModel<T> : ObservableObject, IDatabaseListViewModel
    where T : DatabaseEntry, new()
{
    public string CategoryName { get; }
    public ObservableCollection<T> Entries { get; }

    IList IDatabaseListViewModel.Entries => Entries;

    private T? selected;
    public T? Selected
    {
        get => selected;
        set
        {
            if (SetProperty(ref selected, value))
            {
                DuplicateCommand.NotifyCanExecuteChanged();
                DeleteCommand.NotifyCanExecuteChanged();
                MoveUpCommand.NotifyCanExecuteChanged();
                MoveDownCommand.NotifyCanExecuteChanged();
            }
        }
    }

    IDatabaseEntry? IDatabaseListViewModel.SelectedEntry
    {
        get => Selected;
        set => Selected = value as T;
    }

    ICommand IDatabaseListViewModel.AddCommand => AddCommand;
    ICommand IDatabaseListViewModel.DuplicateCommand => DuplicateCommand;
    ICommand IDatabaseListViewModel.DeleteCommand => DeleteCommand;
    ICommand IDatabaseListViewModel.MoveUpCommand => MoveUpCommand;
    ICommand IDatabaseListViewModel.MoveDownCommand => MoveDownCommand;

    public DatabaseListViewModel(string categoryName, ObservableCollection<T> entries)
    {
        CategoryName = categoryName;
        Entries = entries;
    }

    [RelayCommand]
    private void Add()
    {
        var nextId = Entries.Count == 0 ? 1 : Entries.Max(e => e.Id) + 1;
        var entry = new T { Id = nextId, Name = $"{CategoryName} {nextId}" };
        Entries.Add(entry);
        Selected = entry;
    }

    [RelayCommand(CanExecute = nameof(HasSelection))]
    private void Duplicate()
    {
        if (Selected is null)
            return;

        var nextId = Entries.Count == 0 ? 1 : Entries.Max(e => e.Id) + 1;
        var copy = new T { Id = nextId, Name = $"{Selected.Name} (복사본)", Note = Selected.Note };
        var index = Entries.IndexOf(Selected);
        Entries.Insert(index + 1, copy);
        Selected = copy;
    }

    [RelayCommand(CanExecute = nameof(HasSelection))]
    private void Delete()
    {
        if (Selected is null)
            return;

        var index = Entries.IndexOf(Selected);
        Entries.RemoveAt(index);
        Selected = Entries.Count == 0 ? null : Entries[Math.Min(index, Entries.Count - 1)];
    }

    [RelayCommand(CanExecute = nameof(CanMoveUp))]
    private void MoveUp()
    {
        var index = Entries.IndexOf(Selected!);
        Entries.Move(index, index - 1);
        MoveUpCommand.NotifyCanExecuteChanged();
        MoveDownCommand.NotifyCanExecuteChanged();
    }

    [RelayCommand(CanExecute = nameof(CanMoveDown))]
    private void MoveDown()
    {
        var index = Entries.IndexOf(Selected!);
        Entries.Move(index, index + 1);
        MoveUpCommand.NotifyCanExecuteChanged();
        MoveDownCommand.NotifyCanExecuteChanged();
    }

    private bool HasSelection => Selected is not null;
    private bool CanMoveUp => Selected is not null && Entries.IndexOf(Selected) > 0;
    private bool CanMoveDown => Selected is not null && Entries.IndexOf(Selected) < Entries.Count - 1;
}
