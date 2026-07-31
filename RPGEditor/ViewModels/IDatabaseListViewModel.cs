using System.Collections;
using System.Windows.Input;
using RPGEditor.Models;

namespace RPGEditor.ViewModels;

public interface IDatabaseListViewModel
{
    string CategoryName { get; }
    IList Entries { get; }
    IDatabaseEntry? SelectedEntry { get; set; }
    ICommand AddCommand { get; }
    ICommand DuplicateCommand { get; }
    ICommand DeleteCommand { get; }
    ICommand MoveUpCommand { get; }
    ICommand MoveDownCommand { get; }
}
